<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportPeriodRequest;
use App\Models\Incidencia;
use App\Models\Mascota;
use App\Models\PostulacionVoluntariado;
use App\Models\SeguimientoAdopcion;
use App\Models\SolicitudAdopcion;
use App\Models\User;
use App\Models\VisitaSeguimiento;
use App\Models\Voluntario;
use App\Services\ExportadorReporte;
use App\Services\ReporteAdministrador;
use App\Support\PeriodoReporte;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteController extends Controller
{
    /**
     * Los recuentos «por estado» describen el estado actual (sin período). Con un período, los
     * indicadores de eventos y las adopciones se calculan con ReporteAdministrador (ver allí qué fecha
     * usa cada uno) junto con su período anterior. Sin período, las adopciones también se agrupan por
     * resuelta_at; filas aceptadas sin esa fecha solo se incluyen en el total histórico.
     */
    public function index(ReportPeriodRequest $request, ReporteAdministrador $reportes): View
    {
        // Pestaña de categoría (solo la pantalla; el PDF y el Excel descargan el reporte completo).
        $categoria = $request->query('categoria');
        $categoria = is_string($categoria) && array_key_exists($categoria, self::CATEGORIAS) ? $categoria : 'general';

        $periodoReporte = $request->periodoReporte();
        [$registros, $desglose] = $categoria === 'general' ? [null, []] : $this->listaDeCategoria($categoria, $periodoReporte);

        return view('admin.reportes.index', [
            ...$this->datos($request, $reportes),
            'categoria' => $categoria,
            'categorias' => self::CATEGORIAS,
            'registros' => $registros,
            'desglose' => $desglose,
        ]);
    }

    /**
     * Lista de consulta de una categoría (solo lectura, 25 por página): los registros creados en el período, o todos
     * sin período. Devuelve también el desglose por estado o rol de esa misma lista, para la línea de totales.
     *
     * @return array{0: LengthAwarePaginator, 1: array<string, int>}
     */
    private function listaDeCategoria(string $categoria, ?PeriodoReporte $periodo): array
    {
        [$modelo, $columna] = match ($categoria) {
            'mascotas' => [Mascota::class, 'estado'],
            'solicitudes' => [SolicitudAdopcion::class, 'estado'],
            'seguimientos' => [SeguimientoAdopcion::class, 'estado'],
            'voluntariado' => [PostulacionVoluntariado::class, 'estado'],
            'usuarios' => [User::class, 'rol'],
        };

        $base = $modelo::query();
        if ($periodo !== null) {
            $periodo->aplicar($base, 'created_at');
        }

        // Desglose de la misma lista (columna fija del match, sin datos del usuario).
        $desglose = $base->clone()->selectRaw("{$columna} as clave, COUNT(*) as total")->groupBy($columna)
            ->pluck('total', 'clave')->map(fn ($total) => (int) $total)->all();

        $lista = match ($categoria) {
            'solicitudes' => $base->with(['solicitante:id,nombres,apellidos', 'mascota:id,nombre']),
            'seguimientos' => $base
                ->with(['solicitud:id,solicitante_id,mascota_id', 'solicitud.mascota:id,nombre', 'solicitud.solicitante:id,nombres,apellidos', 'voluntario:id,nombres,apellidos'])
                ->withCount(['visitas', 'incidencias as incidencias_pendientes_count' => fn ($q) => $q->where('estado', '<>', 'atendida')]),
            'voluntariado' => $base->with(['usuario:id,nombres,apellidos', 'voluntario:id,postulacion_id,estado']),
            default => $base,
        };

        return [$lista->latest()->paginate(25)->withQueryString(), $desglose];
    }

    /** Pestañas de la pantalla de reportes. */
    public const CATEGORIAS = [
        'general' => 'Resumen',
        'mascotas' => 'Mascotas',
        'solicitudes' => 'Solicitudes',
        'seguimientos' => 'Seguimientos',
        'voluntariado' => 'Voluntariado',
        'usuarios' => 'Usuarios',
    ];

    /** Descarga en PDF de los mismos datos que muestra la pantalla para el período solicitado. */
    public function pdf(ReportPeriodRequest $request, ReporteAdministrador $reportes, ExportadorReporte $exportador): Response
    {
        return $exportador->pdf($this->datos($request, $reportes));
    }

    /** Descarga en Excel (.xlsx) de los mismos datos que muestra la pantalla para el período solicitado. */
    public function excel(ReportPeriodRequest $request, ReporteAdministrador $reportes, ExportadorReporte $exportador): StreamedResponse
    {
        return $exportador->excel($this->datos($request, $reportes));
    }

    /** Datos del reporte para un período validado: los comparten la pantalla y las exportaciones. */
    private function datos(ReportPeriodRequest $request, ReporteAdministrador $reportes): array
    {
        $periodo = $request->validated();
        $periodoReporte = $request->periodoReporte();
        $mascotasPorEstado = $this->countsByState(Mascota::query(), ['disponible', 'reservada', 'adoptada']);
        $mascotasPorEspecie = $this->countsByState(Mascota::query(), ['perro', 'gato'], 'especie');
        $solicitudesPorEstado = $this->countsByState(SolicitudAdopcion::query(), ['pendiente', 'en_espera', 'aprobada', 'aceptada', 'rechazada', 'cancelada', 'cerrada', 'no_concretada']);
        $adopcionesSinFechaResolucion = $reportes->adopcionesSinFechaResolucion();
        $seguimientosPorEstado = $this->countsByState(SeguimientoAdopcion::query(), ['sin_asignar', 'activo', 'cerrado']);
        $incidenciasPorEstado = $this->countsByState(Incidencia::query(), ['pendiente', 'en_proceso', 'atendida']);
        $postulacionesPorEstado = $this->countsByState(PostulacionVoluntariado::query(), ['pendiente', 'aprobada', 'rechazada', 'cancelada']);
        $voluntariosPorEstado = $this->countsByState(Voluntario::query(), ['activo', 'inactivo']);
        // Pestañas por categoría: visitas, cuentas por rol y por estado, y altas del período (solo lectura).
        $visitasPorEstado = $this->countsByState(VisitaSeguimiento::query(), ['programada', 'realizada', 'no_asistio']);
        $usuariosPorRol = $this->countsByState(User::query(), ['administrador', 'voluntario', 'ciudadano'], 'rol');
        $usuariosActivos = User::query()->where('estado', true)->count();

        if ($periodoReporte !== null) {
            // Adopciones completadas por fecha de resolución (resuelta_at), solo solicitudes aceptadas.
            $indicadoresPeriodo = $reportes->indicadores($periodoReporte);
            $totalAdopciones = $indicadoresPeriodo['adopciones_completadas']['actual'];
            $adopcionesPorFecha = $reportes->adopcionesPorDia($periodoReporte);
        } else {
            // Sin período: todas las solicitudes aceptadas, como antes.
            $indicadoresPeriodo = null;
            $adopciones = $this->applyPeriod(SolicitudAdopcion::query()->where('estado', 'aceptada'), $periodo);
            $totalAdopciones = $adopciones->count();
            // Sin período también se agrupan solo las adopciones concretadas con fecha de resolución.
            // Las filas sin resuelta_at siguen en el total histórico, pero no reciben fecha inventada.
            $adopcionesPorFecha = $adopciones->clone()
                ->whereNotNull('resuelta_at')
                ->selectRaw("(resuelta_at AT TIME ZONE 'America/La_Paz')::date as fecha, COUNT(*) as total")
                ->groupByRaw("(resuelta_at AT TIME ZONE 'America/La_Paz')::date")
                ->orderBy('fecha')
                ->pluck('total', 'fecha');
        }

        return [
            'periodo' => $periodo,
            // Parámetros del período para los enlaces de descarga (los mismos que validó la petición).
            'parametrosExportacion' => $periodoReporte === null ? [] : ['periodo' => $periodoReporte->tipo, ...($periodoReporte->tipo === 'personalizado' ? $periodoReporte->fechas() : [])],
            'generadoEn' => now(),
            'periodoReporte' => $periodoReporte,
            'periodoAnterior' => $periodoReporte?->anterior(),
            'indicadoresPeriodo' => $indicadoresPeriodo,
            // Solo con período: gestión y embudo de las solicitudes recibidas en él.
            'gestion' => $periodoReporte ? $reportes->gestion($periodoReporte) : null,
            'embudo' => $periodoReporte ? $reportes->embudo($periodoReporte) : null,
            // Independientes del período: evolución de los últimos 6 meses y estado actual de las mascotas.
            'evolucionMensual' => $reportes->evolucionMensual(),
            'disponibles' => $reportes->mascotasDisponibles(),
            'esperanMasTiempo' => $reportes->esperanMasTiempo(),
            'mascotasPorEstado' => $mascotasPorEstado,
            'mascotasPorEspecie' => $mascotasPorEspecie,
            'solicitudesPorEstado' => $solicitudesPorEstado,
            'seguimientosPorEstado' => $seguimientosPorEstado,
            'incidenciasPorEstado' => $incidenciasPorEstado,
            'postulacionesPorEstado' => $postulacionesPorEstado,
            'voluntariosPorEstado' => $voluntariosPorEstado,
            'totalMascotas' => array_sum($mascotasPorEstado),
            'totalSolicitudes' => array_sum($solicitudesPorEstado),
            'totalAdopciones' => $totalAdopciones,
            'adopcionesSinFechaResolucion' => $adopcionesSinFechaResolucion,
            'totalVoluntarios' => array_sum($voluntariosPorEstado),
            'totalSeguimientos' => array_sum($seguimientosPorEstado),
            'totalIncidencias' => array_sum($incidenciasPorEstado),
            'adopcionesPorFecha' => $adopcionesPorFecha,
            'visitasPorEstado' => $visitasPorEstado,
            'usuariosPorRol' => $usuariosPorRol,
            'totalUsuarios' => array_sum($usuariosPorRol),
            'usuariosActivos' => $usuariosActivos,
            // Altas en el período (null sin período): cuentas nuevas y mascotas registradas.
            'usuariosNuevos' => $periodoReporte?->aplicar(User::query(), 'created_at')->count(),
            'mascotasNuevas' => $periodoReporte?->aplicar(Mascota::query(), 'created_at')->count(),
        ];
    }

    private function countsByState(Builder $query, array $states, string $column = 'estado'): array
    {
        $counts = $query->selectRaw("{$column}, COUNT(*) as total")->groupBy($column)->pluck('total', $column);

        return collect($states)->mapWithKeys(fn (string $state) => [$state => (int) ($counts[$state] ?? 0)])->all();
    }

    private function applyPeriod(Builder $query, array $periodo): Builder
    {
        return $query
            ->when($periodo['fecha_inicio'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($periodo['fecha_fin'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date));
    }
}
