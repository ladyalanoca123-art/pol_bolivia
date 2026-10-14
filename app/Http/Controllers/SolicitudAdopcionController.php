<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSolicitudAdopcionRequest;
use App\Models\Mascota;
use App\Models\SolicitudAdopcion;
use App\Services\EvaluadorSolicitudAdopcion;
use App\Support\CuestionarioAdopcion as C;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SolicitudAdopcionController extends Controller
{
    public function create(Mascota $mascota): View
    {
        abort_unless($mascota->estado === 'disponible', 404);

        return view('solicitudes-adopcion.create', compact('mascota'));
    }

    public function store(StoreSolicitudAdopcionRequest $request, Mascota $mascota, EvaluadorSolicitudAdopcion $evaluador): RedirectResponse
    {
        $r = $request->validated();
        $evaluacion = $evaluador->evaluar($r);

        $respuestas = collect($r)->except(['zona_aproximada', ...array_keys(C::CONSENTIMIENTOS)])->all();
        $respuestas['consentimientos'] = array_fill_keys(array_keys(C::CONSENTIMIENTOS), true);

        SolicitudAdopcion::create([
            'solicitante_id' => auth()->id(),
            'mascota_id' => $mascota->id,
            'estado' => 'pendiente',
            // Columnas que ya usaba el sistema: el seguimiento copia zona y horario al aceptar.
            'zona_aproximada' => $r['zona_aproximada'],
            'horario_preferido' => collect($r['horarios_visita'])->map(fn ($h) => C::etiqueta('horarios_visita', $h))->join(', '),
            'tiene_otras_mascotas' => $r['otras_mascotas'] === 'si',
            'viven_otras_personas' => (int) $r['adultos'] > 1 || (int) $r['ninos'] > 0,
            'declara_informacion_veraz' => true,
            // Cuestionario A2 y su evaluación (ver app/Services/EvaluadorSolicitudAdopcion.php).
            'respuestas' => $respuestas,
            'version_cuestionario' => C::VERSION,
            'nivel_alerta' => $evaluacion['nivel'],
            'alertas' => $evaluacion['alertas'],
            'version_reglas' => EvaluadorSolicitudAdopcion::VERSION_REGLAS,
        ]);

        return to_route('solicitudes-adopcion.index')
            ->with('status', 'Tu solicitud de adopción fue registrada.');
    }

    public function index(): View
    {
        // Fotos cargadas por adelantado (solo tipo foto, la principal primero) para las tarjetas, sin N+1.
        $solicitudes = auth()->user()->solicitudesAdopcion()
            ->with(['mascota.fotos' => fn ($query) => $query
                ->where('tipo', 'foto')
                ->orderByDesc('es_principal')
                ->orderBy('orden')])
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
