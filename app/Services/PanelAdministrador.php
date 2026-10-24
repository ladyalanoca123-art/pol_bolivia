<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\Incidencia;
use App\Models\Mascota;
use App\Models\PostulacionVoluntariado;
use App\Models\SeguimientoAdopcion;
use App\Models\SolicitudAdopcion;
use App\Models\User;
use App\Models\VisitaSeguimiento;
use App\Models\Voluntario;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Datos del panel principal del administrador: «¿Qué tengo que hacer hoy?».
 *
 * Reúne indicadores, avisos, la bandeja de tareas, la agenda de visitas y la actividad reciente
 * con consultas limitadas (count() y listas acotadas con with()). Las fechas se calculan en la
 * zona horaria de la aplicación (America/La_Paz). Umbrales y límites: config/pol.php, clave panel.
 */
class PanelAdministrador
{
    /** Modelos cuya auditoría aparece en la actividad reciente. */
    public const MODELOS_ACTIVIDAD = [
        'Mascota', 'SolicitudAdopcion', 'SeguimientoAdopcion',
        'VisitaSeguimiento', 'Incidencia', 'PostulacionVoluntariado',
    ];

    /** Orden de la bandeja por nivel de alerta; las solicitudes sin nivel van al final. */
    private const ORDEN_ALERTA = "case nivel_alerta when 'rojo' then 0 when 'amarillo' then 1 when 'verde' then 2 else 3 end";

    private const ORDEN_INCIDENCIA = "case estado when 'pendiente' then 0 else 1 end";

    /** Todo lo que necesita la vista del panel. */
    public function datos(User $administrador): array
    {
        $ahora = CarbonImmutable::now();

        return [
            'encabezado' => $this->encabezado($administrador, $ahora),
            'indicadores' => $this->indicadores($ahora),
            'avisos' => $this->avisos($ahora),
            'bandeja' => $this->bandeja(),
            'agenda' => $this->agenda($ahora),
            'actividad' => $this->actividad(),
        ];
    }

    public function encabezado(User $administrador, CarbonInterface $ahora): array
    {
        return [
            'saludo' => self::saludo($ahora),
            'nombre' => Str::of((string) $administrador->nombres)->trim()->before(' ')->toString(),
            'fecha' => $ahora->translatedFormat('l, j \d\e F \d\e Y'),
        ];
    }

    /** Buenos días de 05:00 a 11:59, buenas tardes de 12:00 a 18:59 y buenas noches de 19:00 a 04:59. */
    public static function saludo(CarbonInterface $hora): string
    {
        return match (true) {
            $hora->hour >= 5 && $hora->hour < 12 => 'Buenos días',
            $hora->hour >= 12 && $hora->hour < 19 => 'Buenas tardes',
            default => 'Buenas noches',
        };
    }

    public function indicadores(CarbonInterface $ahora): array
    {
        $dias = 7;
        $inicioActual = $ahora->subDays($dias);
        $inicioAnterior = $ahora->subDays($dias * 2);

        $nuevas = SolicitudAdopcion::query()->where('created_at', '>=', $inicioActual)->where('created_at', '<=', $ahora)->count();
        $anteriores = SolicitudAdopcion::query()->where('created_at', '>=', $inicioAnterior)->where('created_at', '<', $inicioActual)->count();

        return [
            'solicitudesNuevas' => $nuevas,
            'solicitudesSemanaAnterior' => $anteriores,
            'variacion' => self::variacion($nuevas, $anteriores),
            'solicitudesEnEvaluacion' => SolicitudAdopcion::query()->where('estado', 'en_espera')->count(),
            'seguimientosActivos' => SeguimientoAdopcion::query()->where('estado', 'activo')->count(),
            'incidenciasAbiertas' => $this->incidenciasAbiertas()->count(),
        ];
    }

    /**
     * Variación de la semana actual frente a la anterior.
     *
     * @return array{tipo: 'aumento'|'disminucion'|'igual'|'nuevo', diferencia: int}
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
        ];
    }

    /**
     * Avisos de situaciones que requieren acción. Se devuelven todos, también los que tienen
     * total 0, para que la vista decida cuáles mostrar.
     *
     * @return array<string, array{total: int, texto: string, accion: string, ruta: string, parametros: array}>
     */
    public function avisos(CarbonInterface $ahora): array
    {
        $diasSolicitud = (int) config('pol.panel.solicitud_dias_alerta');
        $diasAprobada = (int) config('pol.panel.aprobada_dias_alerta');
        $diasSeguimiento = (int) config('pol.panel.seguimiento_sin_asignar_dias');
        $diasPostulacion = (int) config('pol.panel.postulacion_dias_alerta');
        $hoy = $ahora->toDateString();

        $solicitudes = SolicitudAdopcion::query()->where('estado', 'pendiente')->where('created_at', '<', $ahora->subDays($diasSolicitud))->count();
        $entregasPendientes = SolicitudAdopcion::query()->where('estado', 'aprobada')->whereNotNull('aprobada_at')
            ->where('aprobada_at', '<', $ahora->subDays($diasAprobada))->count();
        $seguimientos = SeguimientoAdopcion::query()->where('estado', 'sin_asignar')->where('created_at', '<', $ahora->subDays($diasSeguimiento))->count();
        $visitas = VisitaSeguimiento::query()->where('estado', 'programada')->where('fecha_programada', '<', $hoy)->count();
        $postulaciones = PostulacionVoluntariado::query()->where('estado', 'pendiente')->where('created_at', '<', $ahora->subDays($diasPostulacion))->count();
        // Control de seguridad: el trigger trigger_validar_minimo_fotos ya impide esta situación.
        $mascotas = Mascota::query()->where('estado', 'disponible')
            ->whereDoesntHave('fotos', fn (Builder $fotos) => $fotos->where('tipo', 'foto'))
            ->count();

        return [
            'mascotas_sin_fotos' => [
                'total' => $mascotas,
                'texto' => self::cantidad($mascotas, 'mascota disponible no tiene foto y no luce', 'mascotas disponibles no tienen foto y no lucen').' en el catálogo.',
                'accion' => 'Agregar fotos',
                'ruta' => 'admin.mascotas.index',
                'parametros' => ['estado' => 'disponible'],
            ],
            'solicitudes_sin_respuesta' => [
                'total' => $solicitudes,
                'texto' => self::cantidad($solicitudes, 'solicitud lleva', 'solicitudes llevan').' más de '.self::dias($diasSolicitud).' sin respuesta.',
                'accion' => 'Revisar',
                'ruta' => 'admin.solicitudes-adopcion.index',
                'parametros' => ['estado' => 'pendiente'],
            ],
            'entregas_aprobadas_pendientes' => [
                'total' => $entregasPendientes,
                'texto' => self::cantidad($entregasPendientes, 'solicitud aprobada lleva', 'solicitudes aprobadas llevan').' más de '.self::dias($diasAprobada).' sin registrar la entrega. No son adopciones concretadas.',
                'accion' => 'Revisar entregas',
                'ruta' => 'admin.solicitudes-adopcion.index',
                'parametros' => ['estado' => 'aprobada'],
            ],
            'seguimientos_sin_asignar' => [
                'total' => $seguimientos,
                'texto' => self::cantidad($seguimientos, 'seguimiento lleva', 'seguimientos llevan').' más de '.self::dias($diasSeguimiento).' sin voluntario asignado.',
                'accion' => 'Asignar',
                'ruta' => 'admin.seguimientos.index',
                'parametros' => [],
            ],
            'visitas_vencidas' => [
                'total' => $visitas,
                'texto' => self::cantidad($visitas, 'visita programada ya pasó su fecha', 'visitas programadas ya pasaron su fecha').' sin registrarse.',
                'accion' => 'Ver seguimientos',
                'ruta' => 'admin.seguimientos.index',
                'parametros' => [],
            ],
            'postulaciones_sin_revisar' => [
                'total' => $postulaciones,
                'texto' => self::cantidad($postulaciones, 'postulación lleva', 'postulaciones llevan').' más de '.self::dias($diasPostulacion).' sin revisión.',
                'accion' => 'Revisar',
                'ruta' => 'admin.postulaciones-voluntariado.index',
                'parametros' => ['estado' => 'pendiente'],
            ],
        ];
    }

    /**
     * Bandeja «Requiere tu atención»: hasta limite_lista registros por lista y el total real.
     *
     * @return array<string, array{items: Collection, total: int}>
     */
    public function bandeja(): array
    {
        $solicitante = 'solicitante:id,nombres,apellidos';
        $mascota = 'mascota:id,nombre,especie';

        return [
            'solicitudesPendientes' => $this->lista(
                SolicitudAdopcion::query()->where('estado', 'pendiente')
                    ->with([$mascota, $solicitante])
                    ->orderByRaw(self::ORDEN_ALERTA)->oldest()->orderBy('id')
            ),
            'solicitudesEnEvaluacion' => $this->lista(
                SolicitudAdopcion::query()->where('estado', 'en_espera')
                    ->with([$mascota, $solicitante])
                    ->oldest()->orderBy('id')
            ),
            'seguimientosSinAsignar' => $this->lista(
                SeguimientoAdopcion::query()->where('estado', 'sin_asignar')
                    ->with(['solicitud:id,mascota_id,solicitante_id', 'solicitud.'.$mascota, 'solicitud.'.$solicitante])
                    ->oldest()->orderBy('id')
            ),
            'incidenciasAbiertas' => $this->lista(
                $this->incidenciasAbiertas()
                    ->with(['reportadoPor:id,nombres,apellidos', 'seguimiento:id,solicitud_id', 'seguimiento.solicitud:id,mascota_id', 'seguimiento.solicitud.'.$mascota])
                    ->orderByRaw(self::ORDEN_INCIDENCIA)->oldest()->orderBy('id')
            ),
            'postulacionesPendientes' => $this->lista(
                PostulacionVoluntariado::query()->where('estado', 'pendiente')
                    ->with('usuario:id,nombres,apellidos')
                    ->oldest()->orderBy('id')
            ),
        ];
    }

    /**
     * Visitas programadas desde hoy hasta hoy + agenda_dias (ambos incluidos). Solo hay fecha, sin hora.
     *
     * @return array{items: Collection, total: int, desde: string, hasta: string}
     */
    public function agenda(CarbonInterface $ahora): array
    {
        $desde = $ahora->toDateString();
        $hasta = $ahora->addDays((int) config('pol.panel.agenda_dias'))->toDateString();

        $consulta = VisitaSeguimiento::query()->where('estado', 'programada')
            ->whereBetween('fecha_programada', [$desde, $hasta])
            ->with([
                'voluntario:id,nombres,apellidos',
                'seguimiento:id,solicitud_id,zona_aproximada',
                'seguimiento.solicitud:id,mascota_id,solicitante_id',
                'seguimiento.solicitud.mascota:id,nombre,especie',
                'seguimiento.solicitud.solicitante:id,nombres,apellidos',
            ])
            ->orderBy('fecha_programada')->orderBy('id');

        return [...$this->lista($consulta), 'desde' => $desde, 'hasta' => $hasta];
    }

    /**
     * Últimas auditorías de los modelos de MODELOS_ACTIVIDAD, preparadas para traducirlas a frases.
     * No se leen los valores completos ni la IP ni el user_agent: solo el estado anterior y nuevo,
     * que nunca contiene datos personales.
     *
     * @return Collection<int, array>
     */
    public function actividad()
    {
        return Auditoria::query()
            ->select(['id', 'usuario_id', 'accion', 'modelo', 'modelo_id', 'created_at'])
            ->selectRaw("valores_anteriores ->> 'estado' as estado_anterior, valores_nuevos ->> 'estado' as estado_nuevo")
            ->whereIn('modelo', self::MODELOS_ACTIVIDAD)
            ->with('usuario:id,nombres,apellidos')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit((int) config('pol.panel.actividad_limite'))
            ->get()
            ->map(fn (Auditoria $auditoria) => [
                'id' => $auditoria->id,
                'accion' => $auditoria->accion,
                'modelo' => $auditoria->modelo,
                'modelo_id' => $auditoria->modelo_id,
                'es_sistema' => $auditoria->usuario_id === null,
                'actor' => match (true) {
                    $auditoria->usuario_id === null => 'El sistema',
                    $auditoria->usuario !== null => trim($auditoria->usuario->nombres.' '.$auditoria->usuario->apellidos),
                    default => 'Un usuario eliminado',
                },
                'estado_anterior' => $auditoria->estado_anterior,
                'estado_nuevo' => $auditoria->estado_nuevo,
                'fecha' => $auditoria->created_at,
            ]);
    }

    /**
     * Indicadores de la vista actual del panel (se retirarán al rediseñarla).
     */
    public function indicadoresActuales(): array
    {
        // Solicitudes que esperan una decisión, agrupadas por nivel de alerta.
        $porRevisar = SolicitudAdopcion::query()
            ->whereIn('estado', ['pendiente', 'en_espera'])
            ->selectRaw('nivel_alerta, COUNT(*) as total')
            ->groupBy('nivel_alerta')
            ->pluck('total', 'nivel_alerta');

        return [
            'mascotas' => Mascota::count(),
            'solicitudesPendientes' => SolicitudAdopcion::where('estado', 'pendiente')->count(),
            'solicitudesEnEvaluacion' => SolicitudAdopcion::where('estado', 'en_espera')->count(),
            'solicitudesRojas' => (int) ($porRevisar['rojo'] ?? 0),
            'solicitudesAmarillas' => (int) ($porRevisar['amarillo'] ?? 0),
            'solicitudesVerdes' => (int) ($porRevisar['verde'] ?? 0),
            'adopcionesAprobadas' => SolicitudAdopcion::where('estado', 'aceptada')->count(),
            'usuarios' => User::count(),
            'voluntariosActivos' => Voluntario::where('estado', 'activo')->count(),
            'postulacionesPendientes' => PostulacionVoluntariado::where('estado', 'pendiente')->count(),
            'incidenciasPendientes' => Incidencia::where('estado', 'pendiente')->count(),
        ];
    }

    private function incidenciasAbiertas(): Builder
    {
        return Incidencia::query()->whereIn('estado', ['pendiente', 'en_proceso']);
    }

    /** @return array{items: Collection, total: int} */
    private function lista(Builder $consulta): array
    {
        return [
            'items' => (clone $consulta)->limit((int) config('pol.panel.limite_lista'))->get(),
            'total' => (clone $consulta)->reorder()->count(),
        ];
    }

    private static function cantidad(int $total, string $singular, string $plural): string
    {
        return $total.' '.($total === 1 ? $singular : $plural);
    }

    private static function dias(int $dias): string
    {
        return $dias.' '.($dias === 1 ? 'día' : 'días');
    }
}
