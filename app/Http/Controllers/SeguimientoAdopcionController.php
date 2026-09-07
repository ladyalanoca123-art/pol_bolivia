<?php

namespace App\Http\Controllers;

use App\Http\Requests\Seguimientos\AttendIncidenciaRequest;
use App\Http\Requests\Seguimientos\CompleteVisitaRequest;
use App\Http\Requests\Seguimientos\StoreIncidenciaRequest;
use App\Http\Requests\Seguimientos\StoreVisitaRequest;
use App\Models\Incidencia;
use App\Models\SeguimientoAdopcion;
use App\Models\VisitaSeguimiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SeguimientoAdopcionController extends Controller
{
    public function index(): View
    {
        $seguimientos = SeguimientoAdopcion::query()->where('voluntario_id', auth()->id())->with('solicitud.mascota')->latest()->paginate(15);
        return view('seguimientos.index', compact('seguimientos'));
    }

    public function show(SeguimientoAdopcion $seguimiento): View
    {
        Gate::authorize('view', $seguimiento);
        $seguimiento->load(['solicitud.mascota', 'visitas', 'incidencias']);
        return view('seguimientos.show', compact('seguimiento'));
    }

    public function storeVisit(StoreVisitaRequest $request, SeguimientoAdopcion $seguimiento): RedirectResponse
    {
        Gate::authorize('work', $seguimiento);
        $seguimiento->visitas()->create([...$request->validated(), 'voluntario_id' => auth()->id(), 'estado' => 'programada']);
        return back()->with('status', 'La visita fue programada.');
    }

    public function completeVisit(CompleteVisitaRequest $request, SeguimientoAdopcion $seguimiento, VisitaSeguimiento $visita): RedirectResponse
    {
        Gate::authorize('work', $seguimiento);
        abort_unless($visita->seguimiento_id === $seguimiento->id && $visita->voluntario_id === auth()->id() && $visita->estado === 'programada', 404);
        $visita->forceFill([...$request->validated(), 'estado' => 'realizada'])->save();
        return back()->with('status', 'La visita fue registrada como realizada.');
    }

    public function storeIncident(StoreIncidenciaRequest $request, SeguimientoAdopcion $seguimiento): RedirectResponse
    {
        Gate::authorize('work', $seguimiento);
        $data = $request->validated();
        if (isset($data['visita_id']) && ! $seguimiento->visitas()->whereKey($data['visita_id'])->exists()) abort(404);
        $seguimiento->incidencias()->create([...$data, 'reportado_por' => auth()->id(), 'estado' => 'pendiente']);
        return back()->with('status', 'La incidencia fue registrada.');
    }

    public function attendIncident(AttendIncidenciaRequest $request, SeguimientoAdopcion $seguimiento, Incidencia $incidencia): RedirectResponse
    {
        abort_unless($incidencia->seguimiento_id === $seguimiento->id, 404);
        Gate::authorize('attend', $incidencia);
        $incidencia->forceFill([...$request->validated(), 'estado' => 'atendida', 'atendida_por' => auth()->id(), 'atendida_at' => now()])->save();
        return back()->with('status', 'La incidencia fue atendida.');
    }
}
