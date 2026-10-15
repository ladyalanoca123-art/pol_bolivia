<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportPeriodRequest;
use App\Models\Incidencia;
use App\Models\Mascota;
use App\Models\PostulacionVoluntariado;
use App\Models\SeguimientoAdopcion;
use App\Models\SolicitudAdopcion;
use App\Models\Voluntario;
use App\Services\ExportadorReporte;
use App\Services\ReporteAdministrador;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteController extends Controller
{
    /**
     * Los recuentos «por estado» describen el estado actual (sin período). Con un período, los
     * indicadores de eventos y las adopciones se calculan con ReporteAdministrador (ver allí qué fecha
     * usa cada uno) junto con su período anterior. Sin parámetros se conserva el cálculo anterior.
     */
    public function index(ReportPeriodRequest $request, ReporteAdministrador $reportes): View
    {
        return view('admin.reportes.index', $this->datos($request, $reportes));
    }

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
        $mascotasPorEstado = $this->countsByState(Mascota::query(), ['disponible', 'adoptada']);
        $mascotasPorEspecie = $this->countsByState(Mascota::query(), ['perro', 'gato'], 'especie');
        $solicitudesPorEstado = $this->countsByState(SolicitudAdopcion::query(), ['pendiente', 'en_espera', 'aceptada', 'rechazada', 'cancelada', 'cerrada']);
        $seguimientosPorEstado = $this->countsByState(SeguimientoAdopcion::query(), ['sin_asignar', 'activo', 'cerrado']);
        $incidenciasPorEstado = $this->countsByState(Incidencia::query(), ['pendiente', 'en_proceso', 'atendida']);
        $postulacionesPorEstado = $this->countsByState(PostulacionVoluntariado::query(), ['pendiente', 'aprobada', 'rechazada', 'cancelada']);
        $voluntariosPorEstado = $this->countsByState(Voluntario::query(), ['activo', 'inactivo']);

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
            $adopcionesPorFecha = $adopciones->clone()
                ->selectRaw('DATE(created_at) as fecha, COUNT(*) as total')
                ->groupByRaw('DATE(created_at)')
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
            'totalVoluntarios' => array_sum($voluntariosPorEstado),
            'totalSeguimientos' => array_sum($seguimientosPorEstado),
            'totalIncidencias' => array_sum($incidenciasPorEstado),
            'adopcionesPorFecha' => $adopcionesPorFecha,
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
