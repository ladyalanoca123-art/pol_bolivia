<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterSolicitudesAdopcionRequest;
use App\Models\SolicitudAdopcion;
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
}
