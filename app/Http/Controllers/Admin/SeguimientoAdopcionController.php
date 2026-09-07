<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seguimientos\AssignVolunteerRequest;
use App\Models\SeguimientoAdopcion;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SeguimientoAdopcionController extends Controller
{
    public function index(): View
    {
        $seguimientos = SeguimientoAdopcion::query()->with(['solicitud.mascota', 'solicitud.solicitante', 'voluntario'])->latest()->paginate(15);
        return view('admin.seguimientos.index', compact('seguimientos'));
    }

    public function show(SeguimientoAdopcion $seguimiento): View
    {
        $seguimiento->load(['solicitud.mascota', 'solicitud.solicitante', 'voluntario', 'visitas', 'incidencias']);
        $voluntarios = User::query()->where('rol', 'voluntario')->where('estado', true)->orderBy('nombres')->get();
        return view('admin.seguimientos.show', compact('seguimiento', 'voluntarios'));
    }

    public function assign(AssignVolunteerRequest $request, SeguimientoAdopcion $seguimiento): RedirectResponse
    {
        Gate::authorize('assign', $seguimiento);
        $seguimiento->forceFill([
            'voluntario_id' => $request->integer('voluntario_id'),
            'estado' => 'activo',
            'fecha_inicio' => now(),
        ])->save();
        return back()->with('status', 'El voluntario fue asignado y el seguimiento fue activado.');
    }

    public function close(SeguimientoAdopcion $seguimiento): RedirectResponse
    {
        Gate::authorize('close', $seguimiento);
        $seguimiento->forceFill(['estado' => 'cerrado', 'fecha_cierre' => now()])->save();
        return back()->with('status', 'El seguimiento fue cerrado.');
    }
}
