<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterSolicitudesAdopcionRequest;
use App\Models\SolicitudAdopcion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SolicitudAdopcionController extends Controller
{
    public function index(FilterSolicitudesAdopcionRequest $request): View
    {
        $filters = $request->validated();

        $solicitudes = SolicitudAdopcion::query()
            ->with(['solicitante', 'mascota'])
            ->when($filters['estado'] ?? null, fn ($query, string $estado) => $query->where('estado', $estado))
            ->when($filters['buscar'] ?? null, function ($query, string $buscar): void {
                $query->where(function ($query) use ($buscar): void {
                    $query->whereHas('solicitante', fn ($query) => $query
                        ->where('nombres', 'ilike', "%{$buscar}%")
                        ->orWhere('apellidos', 'ilike', "%{$buscar}%"))
                        ->orWhereHas('mascota', fn ($query) => $query->where('nombre', 'ilike', "%{$buscar}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.solicitudes-adopcion.index', compact('solicitudes', 'filters'));
    }

    public function show(SolicitudAdopcion $solicitud): View
    {
        $solicitud->load(['solicitante', 'mascota']);

        return view('admin.solicitudes-adopcion.show', compact('solicitud'));
    }

    public function accept(SolicitudAdopcion $solicitud): RedirectResponse
    {
        Gate::authorize('accept', $solicitud);

        $solicitud->forceFill(['estado' => 'aceptada'])->save();

        return to_route('admin.solicitudes-adopcion.show', $solicitud)
            ->with('status', 'La solicitud de adopción fue aceptada.');
    }

    public function reject(SolicitudAdopcion $solicitud): RedirectResponse
    {
        Gate::authorize('reject', $solicitud);

        $solicitud->forceFill(['estado' => 'rechazada'])->save();

        return to_route('admin.solicitudes-adopcion.show', $solicitud)
            ->with('status', 'La solicitud de adopción fue rechazada.');
    }
}
