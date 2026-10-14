<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostulacionVoluntariadoRequest;
use App\Models\PostulacionVoluntariado;
use App\Support\CuestionarioVoluntariado as V;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PostulacionVoluntariadoController extends Controller
{
    public function create(): View
    {
        // Si ya tiene una postulación pendiente, la vista muestra un aviso en lugar del formulario.
        // StorePostulacionVoluntariadoRequest sigue bloqueando el envío como respaldo.
        $tienePendiente = auth()->user()->postulacionesVoluntariado()->where('estado', 'pendiente')->exists();

        return view('postulaciones-voluntariado.create', compact('tienePendiente'));
    }

    public function store(StorePostulacionVoluntariadoRequest $request): RedirectResponse
    {
        $r = $request->validated();

        PostulacionVoluntariado::create([
            'usuario_id' => auth()->id(),
            'estado' => 'pendiente',
            'version_cuestionario' => V::VERSION,
            'motivacion' => $r['motivacion'],
            'experiencia_previa' => $r['experiencia_previa'] === 'si',
            'experiencia_detalle' => $r['experiencia_detalle'] ?? null,
            'areas_interes' => array_values($r['areas_interes']),
            'dias_disponibles' => array_values($r['dias_disponibles']),
            'franjas_horarias' => array_values($r['franjas_horarias']),
            // Columnas que PostgreSQL copia al perfil del voluntario al aprobar.
            'disponibilidad' => V::disponibilidadTexto($r['dias_disponibles'], $r['franjas_horarias']),
            'zona_preferencia' => $r['zona_preferencia'],
            'habilidades' => filled($r['habilidades'] ?? null) ? $r['habilidades'] : null,
            'confirma_mayor_edad' => true,
            'declara_informacion_veraz' => true,
        ]);

        return to_route('postulaciones-voluntariado.index')->with('status', 'Tu postulación fue enviada.');
    }

    public function index(): View
    {
        $postulaciones = auth()->user()->postulacionesVoluntariado()->latest()->paginate(15);
        // Sobre todas las postulaciones, no solo la página actual: misma condición que bloquea
        // una nueva postulación en StorePostulacionVoluntariadoRequest.
        $tienePendiente = auth()->user()->postulacionesVoluntariado()->where('estado', 'pendiente')->exists();

        return view('postulaciones-voluntariado.index', compact('postulaciones', 'tienePendiente'));
    }

    public function show(PostulacionVoluntariado $postulacion): View
    {
        abort_unless($postulacion->usuario_id === auth()->id(), 404);

        return view('postulaciones-voluntariado.show', compact('postulacion'));
    }
}
