<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeguimientoAdopcion;
use App\Models\Voluntario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class VoluntarioController extends Controller
{
    public function index(): View
    {
        // Primero los activos (expresión fija, sin datos del usuario).
        $voluntarios = Voluntario::query()->with(['usuario', 'postulacion'])
            ->orderByRaw("CASE WHEN estado = 'activo' THEN 0 ELSE 1 END")
            ->latest()->paginate(15);

        // Carga de trabajo: seguimientos activos de cada voluntario de esta página (el seguimiento guarda el id de usuario).
        $seguimientosActivos = SeguimientoAdopcion::query()
            ->where('estado', 'activo')
            ->whereIn('voluntario_id', $voluntarios->pluck('usuario_id'))
            ->selectRaw('voluntario_id, count(*) as total')
            ->groupBy('voluntario_id')
            ->pluck('total', 'voluntario_id');

        return view('admin.voluntarios.index', compact('voluntarios', 'seguimientosActivos'));
    }

    public function show(Voluntario $voluntario): View
    {
        $voluntario->load(['usuario', 'postulacion']);

        return view('admin.voluntarios.show', compact('voluntario'));
    }

    public function deactivate(Voluntario $voluntario): RedirectResponse
    {
        Gate::authorize('deactivate', $voluntario);
        $voluntario->forceFill(['estado' => 'inactivo'])->save();

        return to_route('admin.voluntarios.show', $voluntario)->with('status', 'El perfil de voluntario fue desactivado.');
    }
}
