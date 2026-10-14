<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DecideSolicitudAdopcionRequest;
use App\Http\Requests\Admin\FilterSolicitudesAdopcionRequest;
use App\Models\Auditoria;
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
            ->when($filters['nivel_alerta'] ?? null, fn ($query, string $nivel) => $query->where('nivel_alerta', $nivel))
            ->when($filters['buscar'] ?? null, function ($query, string $buscar): void {
                $query->where(function ($query) use ($buscar): void {
                    $query->whereHas('solicitante', fn ($query) => $query
                        ->where('nombres', 'ilike', "%{$buscar}%")
                        ->orWhere('apellidos', 'ilike', "%{$buscar}%"))
                        ->orWhereHas('mascota', fn ($query) => $query->where('nombre', 'ilike', "%{$buscar}%"));
                });
            })
            // Primero las que esperan decisión; dentro de ellas, rojas, amarillas y verdes.
            // Expresiones fijas, sin datos del usuario.
            ->orderByRaw("CASE WHEN estado IN ('pendiente', 'en_espera') THEN 0 ELSE 1 END")
            ->orderByRaw("CASE nivel_alerta WHEN 'rojo' THEN 0 WHEN 'amarillo' THEN 1 WHEN 'verde' THEN 2 ELSE 3 END")
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.solicitudes-adopcion.index', compact('solicitudes', 'filters'));
    }

    public function show(SolicitudAdopcion $solicitud): View
    {
        $solicitud->load(['solicitante', 'mascota']);

        // Historial de decisiones a partir de la auditoría (cambios de estado y motivos).
        $historial = Auditoria::query()
            ->with('usuario:id,nombres,apellidos')
            ->where('modelo', 'SolicitudAdopcion')
            ->where('modelo_id', $solicitud->id)
            ->oldest('created_at')
            ->oldest('id')
            ->get()
            ->filter(fn (Auditoria $a) => $a->accion === 'creado'
                || array_key_exists('estado', $a->valores_nuevos ?? [])
                || array_key_exists('comentarios_admin', $a->valores_nuevos ?? []));

        return view('admin.solicitudes-adopcion.show', compact('solicitud', 'historial'));
    }

    /** Pendiente → En evaluación (entrevista o visita). La mascota sigue disponible. */
    public function evaluate(DecideSolicitudAdopcionRequest $request, SolicitudAdopcion $solicitud): RedirectResponse
    {
        Gate::authorize('evaluate', $solicitud);

        $solicitud->forceFill([
            'estado' => 'en_espera',
            'comentarios_admin' => $request->validated('comentarios_admin'),
        ])->save();

        return to_route('admin.solicitudes-adopcion.show', $solicitud)
            ->with('status', 'La solicitud pasó a evaluación: coordina la entrevista o la visita.');
    }

    /** Registra un pedido de información sin cambiar el estado. */
    public function requestInformation(DecideSolicitudAdopcionRequest $request, SolicitudAdopcion $solicitud): RedirectResponse
    {
        Gate::authorize('requestInformation', $solicitud);

        $solicitud->forceFill(['comentarios_admin' => $request->validated('comentarios_admin')])->save();

        return to_route('admin.solicitudes-adopcion.show', $solicitud)
            ->with('status', 'Se registró la solicitud de información. El estado no cambió.');
    }

    /** En evaluación → Aceptada. PostgreSQL adopta la mascota, cierra las demás solicitudes y crea el seguimiento. */
    public function accept(DecideSolicitudAdopcionRequest $request, SolicitudAdopcion $solicitud): RedirectResponse
    {
        Gate::authorize('accept', $solicitud);

        $solicitud->forceFill([
            'estado' => 'aceptada',
            'comentarios_admin' => $request->validated('comentarios_admin'),
        ])->save();

        return to_route('admin.solicitudes-adopcion.show', $solicitud)
            ->with('status', 'La solicitud de adopción fue aceptada.');
    }

    public function reject(DecideSolicitudAdopcionRequest $request, SolicitudAdopcion $solicitud): RedirectResponse
    {
        Gate::authorize('reject', $solicitud);

        $solicitud->forceFill([
            'estado' => 'rechazada',
            'comentarios_admin' => $request->validated('comentarios_admin'),
        ])->save();

        return to_route('admin.solicitudes-adopcion.show', $solicitud)
            ->with('status', 'La solicitud de adopción fue rechazada.');
    }
}
