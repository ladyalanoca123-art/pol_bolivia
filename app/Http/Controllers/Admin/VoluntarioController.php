<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voluntario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class VoluntarioController extends Controller
{
    public function index(): View
    {
        $voluntarios = Voluntario::query()->with(['usuario', 'postulacion'])->latest()->paginate(15);

        return view('admin.voluntarios.index', compact('voluntarios'));
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
