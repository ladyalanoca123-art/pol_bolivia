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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class ReporteController extends Controller
{
    public function index(ReportPeriodRequest $request): View
    {
        $periodo = $request->validated();
        $mascotasPorEstado = $this->countsByState(Mascota::query(), ['borrador', 'disponible', 'adoptada']);
        $mascotasPorEspecie = $this->countsByState(Mascota::query(), ['perro', 'gato'], 'especie');
        $solicitudesPorEstado = $this->countsByState(SolicitudAdopcion::query(), ['pendiente', 'en_espera', 'aceptada', 'rechazada', 'cancelada', 'cerrada']);
        $seguimientosPorEstado = $this->countsByState(SeguimientoAdopcion::query(), ['sin_asignar', 'activo', 'cerrado']);
        $incidenciasPorEstado = $this->countsByState(Incidencia::query(), ['pendiente', 'en_proceso', 'atendida']);
        $postulacionesPorEstado = $this->countsByState(PostulacionVoluntariado::query(), ['pendiente', 'aprobada', 'rechazada', 'cancelada']);
        $voluntariosPorEstado = $this->countsByState(Voluntario::query(), ['activo', 'inactivo']);

        $adopciones = $this->applyPeriod(SolicitudAdopcion::query()->where('estado', 'aceptada'), $periodo);
        $adopcionesPorFecha = $adopciones->clone()
            ->selectRaw('DATE(created_at) as fecha, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('fecha')
            ->pluck('total', 'fecha');

        return view('admin.reportes.index', [
            'periodo' => $periodo,
            'mascotasPorEstado' => $mascotasPorEstado,
            'mascotasPorEspecie' => $mascotasPorEspecie,
            'solicitudesPorEstado' => $solicitudesPorEstado,
            'seguimientosPorEstado' => $seguimientosPorEstado,
            'incidenciasPorEstado' => $incidenciasPorEstado,
            'postulacionesPorEstado' => $postulacionesPorEstado,
            'voluntariosPorEstado' => $voluntariosPorEstado,
            'totalMascotas' => array_sum($mascotasPorEstado),
            'totalSolicitudes' => array_sum($solicitudesPorEstado),
            'totalAdopciones' => $adopciones->count(),
            'totalVoluntarios' => array_sum($voluntariosPorEstado),
            'totalSeguimientos' => array_sum($seguimientosPorEstado),
            'totalIncidencias' => array_sum($incidenciasPorEstado),
            'adopcionesPorFecha' => $adopcionesPorFecha,
        ]);
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
