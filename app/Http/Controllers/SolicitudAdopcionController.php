<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSolicitudAdopcionRequest;
use App\Models\Mascota;
use App\Models\SolicitudAdopcion;
use App\Services\EvaluadorSolicitudAdopcion;
use App\Support\CuestionarioAdopcion as C;
use App\Support\ErrorBaseDatos;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SolicitudAdopcionController extends Controller
{
    public function create(Mascota $mascota): View|RedirectResponse
    {
        abort_unless($mascota->estado === 'disponible', 404);

        // Con el máximo de solicitudes activas no se muestra el cuestionario: no podría enviarse.
        if (auth()->user()->solicitudesAdopcion()->whereIn('estado', SolicitudAdopcion::ESTADOS_ACTIVOS)->count() >= SolicitudAdopcion::MAX_ACTIVAS) {
            return to_route('solicitudes-adopcion.index')->withErrors(['mascota' => SolicitudAdopcion::LIMITE_ALCANZADO]);
        }

        return view('solicitudes-adopcion.create', compact('mascota'));
    }

    public function store(StoreSolicitudAdopcionRequest $request, Mascota $mascota, EvaluadorSolicitudAdopcion $evaluador): RedirectResponse
    {
        $r = $request->validated();
        $evaluacion = $evaluador->evaluar($r);

        // El teléfono se guarda en el perfil, no entre las respuestas del cuestionario.
        $respuestas = collect($r)->except(['zona_aproximada', 'telefono_contacto', ...array_keys(C::CONSENTIMIENTOS)])->all();
        $respuestas['consentimientos'] = array_fill_keys(array_keys(C::CONSENTIMIENTOS), true);

        try {
            DB::transaction(function () use ($r, $mascota, $respuestas, $evaluacion): void {
                // Teléfono obligatorio para solicitar: se guarda en «Mi perfil» para la administración y el seguimiento.
                $usuario = auth()->user();
                if ($usuario->telefono !== $r['telefono_contacto']) {
                    $usuario->forceFill(['telefono' => $r['telefono_contacto']])->save();
                }

                SolicitudAdopcion::create([
                    'solicitante_id' => auth()->id(),
                    'mascota_id' => $mascota->id,
                    'estado' => 'pendiente',
                    // Columnas que ya usaba el sistema: el seguimiento copia zona y horario al concretarse la adopción.
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
            });
        } catch (QueryException $exception) {
            // Reglas de PostgreSQL ante envíos simultáneos: límite de solicitudes activas o mascota ya reservada.
            $error = ErrorBaseDatos::mensaje($exception) ?? throw $exception;

            return back()->withInput()->withErrors(['mascota' => $error]);
        }

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

    /** El solicitante cancela su solicitud en trámite. Si estaba aprobada, PostgreSQL libera la mascota. */
    public function cancel(SolicitudAdopcion $solicitud): RedirectResponse
    {
        abort_unless($solicitud->solicitante_id === auth()->id(), 404);
        Gate::authorize('cancel', $solicitud);

        try {
            DB::transaction(fn () => $solicitud->forceFill([
                'estado' => 'cancelada',
                'motivo_cancelacion' => 'Cancelada por el solicitante',
            ])->save());
        } catch (QueryException $exception) {
            $error = ErrorBaseDatos::mensaje($exception) ?? throw $exception;

            return to_route('solicitudes-adopcion.show', $solicitud)->withErrors(['solicitud' => $error]);
        }

        return to_route('solicitudes-adopcion.show', $solicitud)
            ->with('status', 'Cancelaste tu solicitud de adopción.');
    }
}
