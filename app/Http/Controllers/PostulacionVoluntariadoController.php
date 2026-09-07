<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostulacionVoluntariadoRequest;
use App\Models\PostulacionVoluntariado;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PostulacionVoluntariadoController extends Controller
{
    public function create(): View
    {
        return view('postulaciones-voluntariado.create');
    }

    public function store(StorePostulacionVoluntariadoRequest $request): RedirectResponse
    {
        PostulacionVoluntariado::create([
            ...$request->validated(),
            'usuario_id' => auth()->id(),
            'estado' => 'pendiente',
        ]);

        return to_route('postulaciones-voluntariado.index')->with('status', 'Tu postulación fue enviada.');
    }

    public function index(): View
    {
        $postulaciones = auth()->user()->postulacionesVoluntariado()->latest()->paginate(15);

        return view('postulaciones-voluntariado.index', compact('postulaciones'));
    }

    public function show(PostulacionVoluntariado $postulacion): View
    {
        abort_unless($postulacion->usuario_id === auth()->id(), 404);

        return view('postulaciones-voluntariado.show', compact('postulacion'));
    }
}
