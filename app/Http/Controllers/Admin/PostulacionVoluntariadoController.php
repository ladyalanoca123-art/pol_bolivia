<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewPostulacionVoluntariadoRequest;
use App\Models\PostulacionVoluntariado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PostulacionVoluntariadoController extends Controller
{
    public function index(): View
    {
        $postulaciones = PostulacionVoluntariado::query()->with(['usuario', 'revisadoPor'])->latest()->paginate(15);

        return view('admin.postulaciones-voluntariado.index', compact('postulaciones'));
    }

    public function show(PostulacionVoluntariado $postulacion): View
    {
        $postulacion->load(['usuario', 'revisadoPor', 'voluntario']);

        return view('admin.postulaciones-voluntariado.show', compact('postulacion'));
    }

    public function approve(ReviewPostulacionVoluntariadoRequest $request, PostulacionVoluntariado $postulacion): RedirectResponse
    {
        Gate::authorize('approve', $postulacion);
        $postulacion->forceFill([
            'estado' => 'aprobada',
            'comentarios_admin' => $request->validated('comentarios_admin'),
            'revisado_por' => auth()->id(),
            'revisado_at' => now(),
        ])->save();

        return to_route('admin.postulaciones-voluntariado.show', $postulacion)->with('status', 'La postulación fue aprobada.');
    }

    public function reject(ReviewPostulacionVoluntariadoRequest $request, PostulacionVoluntariado $postulacion): RedirectResponse
    {
        Gate::authorize('reject', $postulacion);
        $postulacion->forceFill([
            'estado' => 'rechazada',
            'comentarios_admin' => $request->validated('comentarios_admin'),
            'revisado_por' => auth()->id(),
            'revisado_at' => now(),
        ])->save();

        return to_route('admin.postulaciones-voluntariado.show', $postulacion)->with('status', 'La postulación fue rechazada.');
    }
}
