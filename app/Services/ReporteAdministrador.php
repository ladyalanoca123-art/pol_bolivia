<?php

namespace App\Services;

use App\Models\Mascota;
use App\Support\PeriodoReporte;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores de Reportes por período, con el valor del período anterior para compararlos.
 *
 * Cada indicador se cuenta por la fecha del evento que mide (todas TIMESTAMPTZ):
 *  - solicitudes_recibidas: solicitudes_adopcion.created_at (cuando se envió la solicitud).
 *  - adopciones_completadas: resuelta_at de las solicitudes en estado aceptada. Una solicitud cerrada
 *    (cierre automático al aceptar otra de la misma mascota) nunca es una adopción.
 *  - solicitudes_rechazadas, solicitudes_canceladas y solicitudes_cerradas: resuelta_at con ese estado.
 *    Los estados finales no pueden cambiar (validar_cambio_estado_solicitud), así que estado y
 *    resuelta_at describen la misma resolución. Sin resuelta_at (no recuperable) no se cuentan.
 *  - mascotas_registradas: mascotas.created_at (alta de la mascota).
 *  - postulaciones_recibidas: created_at; postulaciones_aprobadas y postulaciones_rechazadas: revisado_at
 *    (fecha de la revisión del administrador).
 *  - seguimientos_iniciados: fecha_inicio (asignación del voluntario); seguimientos_cerrados: fecha_cierre.
 *  - visitas_realizadas: fecha_realizada de las visitas realizadas (fecha que registra el voluntario).
 *  - incidencias_reportadas: created_at; incidencias_atendidas: atendida_at.
 *
 * Límites: intervalos semiabiertos de PeriodoReporte, enviados con su desplazamiento (-04:00).
 * Una consulta por tabla calcula a la vez el período y el anterior (count(*) FILTER ...).
 */
class ReporteAdministrador
{
    /** tabla => [indicador => [columna de fecha, condición adicional fija o null]] */
    private const INDICADORES = [
        'solicitudes_adopcion' => [
            'solicitudes_recibidas' => ['created_at', null],
            'adopciones_completadas' => ['resuelta_at', "estado = 'aceptada'"],
            'solicitudes_rechazadas' => ['resuelta_at', "estado = 'rechazada'"],
            'solicitudes_canceladas' => ['resuelta_at', "estado = 'cancelada'"],
            'solicitudes_no_concretadas' => ['resuelta_at', "estado = 'no_concretada'"],
            'solicitudes_cerradas' => ['resuelta_at', "estado = 'cerrada'"],
        ],
        'mascotas' => [
            'mascotas_registradas' => ['created_at', null],
        ],
        'postulaciones_voluntariado' => [
            'postulaciones_recibidas' => ['created_at', null],
            'postulaciones_aprobadas' => ['revisado_at', "estado = 'aprobada'"],
            'postulaciones_rechazadas' => ['revisado_at', "estado = 'rechazada'"],
        ],
        'seguimientos_adopcion' => [
            'seguimientos_iniciados' => ['fecha_inicio', null],
            'seguimientos_cerrados' => ['fecha_cierre', "estado = 'cerrado'"],
        ],
        'visitas_seguimiento' => [
            'visitas_realizadas' => ['fecha_realizada', "estado = 'realizada'"],
        ],
        'incidencias' => [
            'incidencias_reportadas' => ['created_at', null],
            'incidencias_atendidas' => ['atendida_at', "estado = 'atendida'"],
        ],
    ];

    /**
     * @return array<string, array{actual: int, anterior: int, variacion: array{tipo: string, diferencia: int, porcentaje: float|null}}>
     */
    public function indicadores(PeriodoReporte $periodo): array
    {
        $anterior = $periodo->anterior();
        $resultado = [];

        foreach (self::INDICADORES as $tabla => $indicadores) {
            $columnas = [];
            $enlaces = [];
            foreach ($indicadores as $nombre => [$fecha, $condicion]) {
                foreach (['actual' => $periodo, 'anterior' => $anterior] as $sufijo => $p) {
                    $filtro = ($condicion ? "{$condicion} and " : '')."{$fecha} >= ?::timestamptz and {$fecha} < ?::timestamptz";
                    $columnas[] = "count(*) filter (where {$filtro}) as {$nombre}__{$sufijo}";
                    array_push($enlaces, ...self::limites($p));
                }
            }
            $fila = (array) DB::selectOne('select '.implode(', ', $columnas)." from {$tabla}", $enlaces);

            foreach (array_keys($indicadores) as $nombre) {
                $actual = (int) $fila["{$nombre}__actual"];
                $previo = (int) $fila["{$nombre}__anterior"];
                $resultado[$nombre] = ['actual' => $actual, 'anterior' => $previo, 'variacion' => self::variacion($actual, $previo)];
            }
        }

        return $resultado;
    }

    /** Cantidad histórica de adopciones aceptadas que no tienen fecha de resolución verificable. */
    public function adopcionesSinFechaResolucion(): int
    {
        return (int) DB::table('solicitudes_adopcion')
            ->where('estado', 'aceptada')
            ->whereNull('resuelta_at')
            ->count();
    }

    /**
     * Adopciones completadas por día de resolución (fecha de La Paz) dentro del período.
     *
     * @return Collection<string, int> 'AAAA-MM-DD' => total
     */
    public function adopcionesPorDia(PeriodoReporte $periodo): Collection
    {
        return collect(DB::select(
            "select to_char((resuelta_at at time zone ?)::date, 'YYYY-MM-DD') as fecha, count(*) as total
             from solicitudes_adopcion
             where estado = 'aceptada' and resuelta_at >= ?::timestamptz and resuelta_at < ?::timestamptz
             group by 1 order by 1",
            [PeriodoReporte::ZONA, ...self::limites($periodo)]
        ))->mapWithKeys(fn ($fila) => [$fila->fecha => (int) $fila->total]);
    }

    /**
     * Indicadores de gestión del período y del anterior (promedios en días; null si no hay casos).
     *  - tasa_aprobacion: adopciones concretadas / (concretadas + rechazadas); excluye entregas no concretadas
     *    y cancelaciones, que no representan una decisión de rechazo.
     *  - tiempo_respuesta: días entre created_at y la decisión administrativa. Usa aprobada_at para aprobaciones
     *    (también aceptadas, no_concretadas o canceladas después de aprobar) y resuelta_at para rechazos.
     *    Aceptadas antiguas sin aprobada_at quedan fuera: resuelta_at puede representar la entrega, no la decisión.
     *    No_concretadas sin aprobación registrada y cancelaciones anteriores a la aprobación también quedan fuera.
     *  - dias_hasta_adopcion: lo mismo, solo aceptadas.
     *  - atencion_incidencias: días entre created_at y atendida_at de las atendidas (atendida_at).
     *  - revision_postulaciones: días entre created_at y revisado_at de las aprobadas y rechazadas.
     *  - visitas_cumplidas: % de realizadas entre realizadas y no_asistio con fecha_programada (DATE) en el
     *    período; las aún programadas no cuentan porque no tienen resultado.
     */
    public function gestion(PeriodoReporte $periodo): array
    {
        $anterior = $periodo->anterior();
        $resultado = [];

        $s = $this->porPeriodo('solicitudes_adopcion', [
            'aceptadas' => "count(*) filter (where estado = 'aceptada' and %resuelta_at%)",
            'rechazadas' => "count(*) filter (where estado = 'rechazada' and %resuelta_at%)",
            'respuesta' => "avg(extract(epoch from (case when estado = 'rechazada' then resuelta_at when estado in ('aprobada', 'aceptada', 'no_concretada', 'cancelada') then aprobada_at end - created_at)) / 86400) filter (where %fecha_decision%)",
            'adopcion' => "avg(extract(epoch from resuelta_at - created_at) / 86400) filter (where estado = 'aceptada' and %resuelta_at%)",
        ], $periodo, $anterior);
        foreach (['actual', 'anterior'] as $p) {
            $resueltas = $s[$p]['aceptadas'] + $s[$p]['rechazadas'];
            $resultado['tasa_aprobacion'][$p] = $resueltas > 0 ? round($s[$p]['aceptadas'] / $resueltas * 100, 1) : null;
        }
        $resultado['tasa_aprobacion'] += ['aceptadas' => (int) $s['actual']['aceptadas'], 'resueltas' => (int) ($s['actual']['aceptadas'] + $s['actual']['rechazadas'])];
        $resultado['tiempo_respuesta'] = self::promedio($s, 'respuesta');
        $resultado['dias_hasta_adopcion'] = self::promedio($s, 'adopcion');

        $i = $this->porPeriodo('incidencias', [
            'atencion' => "avg(extract(epoch from atendida_at - created_at) / 86400) filter (where estado = 'atendida' and %atendida_at%)",
        ], $periodo, $anterior);
        $resultado['atencion_incidencias'] = self::promedio($i, 'atencion');

        $r = $this->porPeriodo('postulaciones_voluntariado', [
            'revision' => "avg(extract(epoch from revisado_at - created_at) / 86400) filter (where estado in ('aprobada', 'rechazada') and %revisado_at%)",
        ], $periodo, $anterior);
        $resultado['revision_postulaciones'] = self::promedio($r, 'revision');

        $v = $this->porPeriodo('visitas_seguimiento', [
            'realizadas' => "count(*) filter (where estado = 'realizada' and %fecha_programada:date%)",
            'no_asistio' => "count(*) filter (where estado = 'no_asistio' and %fecha_programada:date%)",
        ], $periodo, $anterior);
        foreach (['actual', 'anterior'] as $p) {
            $total = $v[$p]['realizadas'] + $v[$p]['no_asistio'];
            $resultado['visitas_cumplidas'][$p] = $total > 0 ? round($v[$p]['realizadas'] / $total * 100, 1) : null;
        }
        $resultado['visitas_cumplidas'] += ['realizadas' => (int) $v['actual']['realizadas'], 'con_resultado' => (int) ($v['actual']['realizadas'] + $v['actual']['no_asistio'])];

        return $resultado;
    }

    /**
     * Embudo de las solicitudes recibidas en el período (created_at), según su estado actual:
     * evaluadas por el equipo (en evaluación, aprobadas, aceptadas, rechazadas o no concretadas),
     * adopciones concretadas (aceptadas) y con seguimiento cerrado. Las canceladas y las cerradas
     * automáticamente no se cuentan como evaluadas.
     *
     * @return array{recibidas: int, evaluadas: int, aceptadas: int, seguimiento_cerrado: int, porcentaje_adopcion: float|null}
     */
    public function embudo(PeriodoReporte $periodo): array
    {
        $fila = DB::selectOne(
            "select count(*) as recibidas,
                    count(*) filter (where s.estado in ('en_espera', 'aprobada', 'aceptada', 'rechazada', 'no_concretada')) as evaluadas,
                    count(*) filter (where s.estado = 'aceptada') as aceptadas,
                    count(*) filter (where s.estado = 'aceptada' and exists (
                        select 1 from seguimientos_adopcion g where g.solicitud_id = s.id and g.estado = 'cerrado'
                    )) as seguimiento_cerrado
             from solicitudes_adopcion s
             where s.created_at >= ?::timestamptz and s.created_at < ?::timestamptz",
            self::limites($periodo)
        );
        $datos = array_map('intval', (array) $fila);

        return [...$datos, 'porcentaje_adopcion' => $datos['recibidas'] > 0 ? round($datos['aceptadas'] / $datos['recibidas'] * 100, 1) : null];
    }

    /**
     * Solicitudes recibidas (created_at) y adopciones completadas (resuelta_at, aceptadas) de los últimos
     * $meses meses calendario en La Paz, incluido el actual (en curso). Independiente del período elegido.
     *
     * @return array<int, array{mes: string, etiqueta: string, en_curso: bool, solicitudes: int, adopciones: int}>
     */
    public function evolucionMensual(int $meses = 6): array
    {
        $actual = CarbonImmutable::now(PeriodoReporte::ZONA)->startOfMonth();
        $desde = $actual->subMonthsNoOverflow($meses - 1);
        $hasta = $actual->addMonthNoOverflow();
        $limites = [$desde->format('Y-m-d H:i:sP'), $hasta->format('Y-m-d H:i:sP')];
        $mes = "to_char(date_trunc('month', %s at time zone '".PeriodoReporte::ZONA."'), 'YYYY-MM')";

        $solicitudes = collect(DB::select('select '.sprintf($mes, 'created_at').' as mes, count(*) as total from solicitudes_adopcion
            where created_at >= ?::timestamptz and created_at < ?::timestamptz group by 1', $limites))->pluck('total', 'mes');
        $adopciones = collect(DB::select('select '.sprintf($mes, 'resuelta_at')." as mes, count(*) as total from solicitudes_adopcion
            where estado = 'aceptada' and resuelta_at >= ?::timestamptz and resuelta_at < ?::timestamptz group by 1", $limites))->pluck('total', 'mes');

        return collect(range(0, $meses - 1))->map(function (int $i) use ($desde, $actual, $solicitudes, $adopciones) {
            $mes = $desde->addMonthsNoOverflow($i);
            $clave = $mes->format('Y-m');

            return [
                'mes' => $clave,
                'etiqueta' => str_replace('.', '', $mes->translatedFormat('M')),
                'en_curso' => $mes->equalTo($actual),
                'solicitudes' => (int) ($solicitudes[$clave] ?? 0),
                'adopciones' => (int) ($adopciones[$clave] ?? 0),
            ];
        })->all();
    }

    /**
     * Mascotas disponibles hoy (estado actual, sin período), por especie y por tamaño.
     *
     * @return array{total: int, especie: array<string, int>, tamano: array<string, int>}
     */
    public function mascotasDisponibles(): array
    {
        $filas = DB::select("select especie, tamano, count(*) as total from mascotas where estado = 'disponible' group by especie, tamano");
        $especie = ['perro' => 0, 'gato' => 0];
        $tamano = ['pequeno' => 0, 'mediano' => 0, 'grande' => 0];
        foreach ($filas as $f) {
            $especie[$f->especie] = ($especie[$f->especie] ?? 0) + (int) $f->total;
            $tamano[$f->tamano] = ($tamano[$f->tamano] ?? 0) + (int) $f->total;
        }

        return ['total' => array_sum($especie), 'especie' => $especie, 'tamano' => $tamano];
    }

    /**
     * Mascotas disponibles que llevan más tiempo esperando hogar (desde su alta, created_at), con los días
     * de espera contados en La Paz.
     *
     * @return Collection<int, array{mascota: Mascota, dias: int}>
     */
    public function esperanMasTiempo(int $limite = 4): Collection
    {
        $hoy = CarbonImmutable::now(PeriodoReporte::ZONA)->startOfDay();

        return Mascota::query()
            ->select(['id', 'nombre', 'especie', 'tamano', 'sexo', 'edad', 'edad_en', 'created_at'])
            ->where('estado', 'disponible')
            ->oldest()->orderBy('id')
            ->limit($limite)
            ->get()
            ->map(fn (Mascota $m) => [
                'mascota' => $m,
                'dias' => (int) round($m->created_at->copy()->setTimezone(PeriodoReporte::ZONA)->startOfDay()->diffInDays($hoy)),
            ]);
    }

    /**
     * Variación frente al período anterior. El porcentaje solo se calcula si el anterior no es cero.
     *
     * @return array{tipo: 'aumento'|'disminucion'|'igual'|'nuevo', diferencia: int, porcentaje: float|null}
     */
    public static function variacion(int $actual, int $anterior): array
    {
        $diferencia = $actual - $anterior;

        return [
            'tipo' => match (true) {
                $anterior === 0 && $actual > 0 => 'nuevo',
                $diferencia > 0 => 'aumento',
                $diferencia < 0 => 'disminucion',
                default => 'igual',
            },
            'diferencia' => abs($diferencia),
            'porcentaje' => $anterior > 0 ? round(abs($diferencia) / $anterior * 100, 1) : null,
        ];
    }

    /**
     * Ejecuta en una sola consulta las expresiones para el período y el anterior. En cada expresión,
     * %columna% se sustituye por el intervalo TIMESTAMPTZ del período y %columna:date% por el de fechas
     * (DATE). Las expresiones son constantes de esta clase: no contienen datos del usuario.
     *
     * @param  array<string, string>  $expresiones
     * @return array{actual: array<string, int|float|null>, anterior: array<string, int|float|null>}
     */
    private function porPeriodo(string $tabla, array $expresiones, PeriodoReporte $periodo, PeriodoReporte $anterior): array
    {
        $columnas = [];
        $enlaces = [];
        foreach (['actual' => $periodo, 'anterior' => $anterior] as $sufijo => $p) {
            foreach ($expresiones as $nombre => $expresion) {
                $columnas[] = preg_replace_callback('/%([a-z_]+)(:date)?%/', function (array $m) use ($p, &$enlaces) {
                    if ($m[1] === 'fecha_decision') {
                        $decision = "case when estado = 'rechazada' then resuelta_at when estado in ('aprobada', 'aceptada', 'no_concretada', 'cancelada') then aprobada_at end";
                        array_push($enlaces, ...self::limites($p));
                        return "({$decision}) >= ?::timestamptz and ({$decision}) < ?::timestamptz";
                    }
                    if (($m[2] ?? '') === ':date') {
                        array_push($enlaces, $p->inicio->toDateString(), $p->finExclusivo->toDateString());

                        return "{$m[1]} >= ?::date and {$m[1]} < ?::date";
                    }
                    array_push($enlaces, ...self::limites($p));

                    return "{$m[1]} >= ?::timestamptz and {$m[1]} < ?::timestamptz";
                }, $expresion)." as {$nombre}__{$sufijo}";
            }
        }

        $fila = (array) DB::selectOne('select '.implode(', ', $columnas)." from {$tabla}", $enlaces);
        $resultado = [];
        foreach (['actual', 'anterior'] as $sufijo) {
            foreach (array_keys($expresiones) as $nombre) {
                $valor = $fila["{$nombre}__{$sufijo}"];
                $resultado[$sufijo][$nombre] = $valor === null ? null : (str_contains((string) $valor, '.') ? (float) $valor : (int) $valor);
            }
        }

        return $resultado;
    }

    /** @return array{actual: float|null, anterior: float|null, diferencia: float|null} */
    private static function promedio(array $datos, string $clave): array
    {
        $actual = $datos['actual'][$clave] === null ? null : round((float) $datos['actual'][$clave], 1);
        $anterior = $datos['anterior'][$clave] === null ? null : round((float) $datos['anterior'][$clave], 1);

        return ['actual' => $actual, 'anterior' => $anterior, 'diferencia' => $actual !== null && $anterior !== null ? round($actual - $anterior, 1) : null];
    }

    /** @return array{0: string, 1: string} */
    private static function limites(PeriodoReporte $periodo): array
    {
        return [$periodo->inicio->format('Y-m-d H:i:sP'), $periodo->finExclusivo->format('Y-m-d H:i:sP')];
    }
}
