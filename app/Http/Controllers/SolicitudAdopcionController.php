<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSolicitudAdopcionRequest;
use App\Models\Mascota;
use App\Models\SolicitudAdopcion;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SolicitudAdopcionController extends Controller
{
    public function create(Mascota $mascota): View
    {
        abort_unless($mascota->estado === 'disponible', 404);

        return view('solicitudes-adopcion.create', compact('mascota'));
    }

    public function store(StoreSolicitudAdopcionRequest $request, Mascota $mascota): RedirectResponse
    {
        SolicitudAdopcion::create([
            ...$request->validated(),
            'solicitante_id' => auth()->id(),
            'mascota_id' => $mascota->id,
            'estado' => 'pendiente',
        ]);

        return to_route('solicitudes-adopcion.index')
            ->with('status', 'Tu solicitud de adopción fue registrada.');
    }

    public function index(): View
    {
        $solicitudes = auth()->user()->solicitudesAdopcion()
            ->with('mascota')
            ->latest()
            ->paginate(15);

        return view('solicitudes-adopcion.index', compact('solicitudes'));
    }

    public function show(SolicitudAdopcion $solicitud): View
    {
        abort_unless($solicitud->solicitante_id === auth()->id(), 404);

        $solicitud->load('mascota');

        return view('solicitudes-adopcion.show', compact('solicitud'));
    }
}
