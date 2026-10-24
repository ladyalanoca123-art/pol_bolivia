<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AprobarSolicitudAdopcionRequest;
use App\Http\Requests\Admin\DecideSolicitudAdopcionRequest;
use App\Http\Requests\Admin\EntregaNoConcretadaRequest;
use App\Http\Requests\Admin\FechaEntregaAcordadaRequest;
use App\Http\Requests\Admin\FilterSolicitudesAdopcionRequest;
use App\Http\Requests\Admin\RegistrarEntregaRequest;
use App\Http\Requests\Admin\RegistrarEntrevistaRequest;
use App\Http\Requests\Admin\RegistrarVisitaPreviaRequest;
use App\Http\Requests\Admin\UploadCommitmentEvidenceRequest;
use App\Models\Auditoria;
use App\Models\SolicitudAdopcion;
use App\Services\ImagenNoProcesada;
use App\Services\ImagenSegura;
use App\Support\ErrorBaseDatos;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\View\View;

/**
 * Flujo de la solicitud: Recibida → En evaluación → Aprobada → Adopción concretada (ver SolicitudAdopcionPolicy).
 * Cada acción se autoriza con la política y PostgreSQL vuelve a comprobar las reglas (validar_cambio_estado_solicitud):
 * si las rechaza (p. ej., otra aprobación simultánea de la misma mascota), se muestra su mensaje sin cambiar nada.
 */
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
            // Primero las que están en trámite (recibidas, en evaluación y aprobadas); dentro de ellas, rojas,
            // amarillas y verdes. Expresiones fijas, sin datos del usuario.
            ->orderByRaw("CASE WHEN estado IN ('pendiente', 'en_espera', 'aprobada') THEN 0 ELSE 1 END")
            ->orderByRaw("CASE nivel_alerta WHEN 'rojo' THEN 0 WHEN 'amarillo' THEN 1 WHEN 'verde' THEN 2 ELSE 3 END")
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.solicitudes-adopcion.index', compact('solicitudes', 'filters'));
    }

    public function show(SolicitudAdopcion $solicitud): View
    {
        $solicitud->load(['solicitante', 'mascota', 'seguimiento']);

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
                || array_key_exists('comentarios_admin', $a->valores_nuevos ?? [])
                || array_key_exists('entrevista_resultado', $a->valores_nuevos ?? [])
                || array_key_exists('visita_previa_resultado', $a->valores_nuevos ?? []));

        return view('admin.solicitudes-adopcion.show', compact('solicitud', 'historial'));
    }

    /** Descarga el formato de compromiso para firma; nunca afirma que el documento esté firmado. */
    public function commitmentPdf(SolicitudAdopcion $solicitud)
    {
        Gate::authorize('viewCommitment', $solicitud);
        $solicitud->load(['solicitante', 'mascota']);

        return Pdf::loadView('admin.solicitudes-adopcion.compromiso-pdf', compact('solicitud'))
            ->setPaper('letter')
            ->download('compromiso-adopcion-'.$solicitud->id.'.pdf');
    }

    /** Guarda evidencia en el disco privado; no marca el compromiso como firmado. */
    public function uploadCommitmentEvidence(UploadCommitmentEvidenceRequest $request, SolicitudAdopcion $solicitud): RedirectResponse
    {
        Gate::authorize('uploadCommitmentEvidence', $solicitud);
        $archivo = $request->file('evidencia_compromiso');
        $disco = Storage::disk('local');
        $anterior = $solicitud->compromiso_foto_path;

        try {
            if (in_array($archivo->getMimeType(), ['image/jpeg', 'image/png'], true)) {
                if (! ImagenSegura::puedeLeer($archivo)) {
                    return back()->withErrors(['evidencia_compromiso' => 'La imagen está dañada o no se puede leer.']);
                }
                $ruta = app(ImagenSegura::class)->guardar($archivo, 'compromisos/'.$solicitud->id, 'local');
                $disco->delete(ImagenSegura::rutaMiniatura($ruta));
            } else {
                $ruta = $archivo->storeAs('compromisos/'.$solicitud->id, Str::uuid().'.pdf', 'local');
                if (! $ruta) {
                    return back()->withErrors(['evidencia_compromiso' => 'No se pudo guardar el archivo. Inténtalo de nuevo.']);
                }
            }
            $solicitud->forceFill(['compromiso_foto_path' => $ruta])->save();
        } catch (ImagenNoProcesada $exception) {
            return back()->withErrors(['evidencia_compromiso' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            if (isset($ruta)) {
                $disco->delete($ruta);
                $disco->delete(ImagenSegura::rutaMiniatura($ruta));
            }
            throw $exception;
        }

        if ($anterior && $anterior !== $ruta && str_starts_with($anterior, 'compromisos/'.$solicitud->id.'/')) {
            $disco->delete($anterior);
            $disco->delete(ImagenSegura::rutaMiniatura($anterior));
        }

        return to_route('admin.solicitudes-adopcion.show', $solicitud)
            ->with('status', 'La evidencia del compromiso se guardó de forma privada. Confirma la firma por separado al registrar la entrega.');
    }

    /** Descarga protegida; nunca se expone una URL o ruta pública. */
    public function downloadCommitmentEvidence(SolicitudAdopcion $solicitud)
    {
        Gate::authorize('viewCommitmentEvidence', $solicitud);
        $ruta = (string) $solicitud->compromiso_foto_path;
        abort_unless(preg_match('#^compromisos/'.$solicitud->id.'/[A-Za-z0-9-]+\.(jpg|png|pdf)$#i', $ruta) && Storage::disk('local')->exists($ruta), 404);
        $extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        $mime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'][$extension];

        return Storage::disk('local')->download($ruta, 'evidencia-compromiso-'.$solicitud->id.'.'.$extension, ['Content-Type' => $mime]);
    }

    /** Recibida → En evaluación. El comentario es opcional. */
    public function evaluate(Request $request, SolicitudAdopcion $solicitud): RedirectResponse
    {
        Gate::authorize('evaluate', $solicitud);

        $datos = $request->validate(
            ['comentarios_admin' => ['nullable', 'string', 'max:1000']],
            ['comentarios_admin.max' => 'El comentario puede tener como máximo 1000 caracteres.'],
        );

        return $this->guardar($solicitud, [
            'estado' => 'en_espera',
            ...(filled($datos['comentarios_admin'] ?? null) ? ['comentarios_admin' => $datos['comentarios_admin']] : []),
        ], 'La solicitud pasó a evaluación. Revisa el formulario y contacta al solicitante por llamada o WhatsApp cuando corresponda.');
    }

    /** Registra un pedido de información sin cambiar el estado. */
    public function requestInformation(DecideSolicitudAdopcionRequest $request, SolicitudAdopcion $solicitud): RedirectResponse
    {
        Gate::authorize('requestInformation', $solicitud);

        return $this->guardar($solicitud, ['comentarios_admin' => $request->validated('comentarios_admin')],
            'Se registró la solicitud de información. El estado no cambió.');
    }

    public function recordInterview(RegistrarEntrevistaRequest $request, SolicitudAdopcion $solicitud): RedirectResponse
    {
        Gate::authorize('recordInterview', $solicitud);

        return $this->guardar($solicitud, $request->validated(), 'La entrevista fue registrada.');
    }

    public function recordHomeVisit(RegistrarVisitaPreviaRequest $request, SolicitudAdopcion $solicitud): RedirectResponse
    {
        Gate::authorize('recordInterview', $solicitud);

        return $this->guardar($solicitud, $request->validated(), 'La visita previa al hogar fue registrada.');
    }

    /** En evaluación → Aprobada. PostgreSQL reserva la mascota y asigna el número de acta. */
    public function approve(AprobarSolicitudAdopcionRequest $request, SolicitudAdopcion $solicitud): RedirectResponse
    {
        Gate::authorize('approve', $solicitud);

        return $this->guardar($solicitud, ['estado' => 'aprobada'],
            fn (SolicitudAdopcion $s) => "La solicitud fue aprobada. {$s->mascota->nombre} quedó reservada. Acta {$s->numero_acta}.");
    }

    public function updateDeliveryDate(FechaEntregaAcordadaRequest $request, SolicitudAdopcion $solicitud): RedirectResponse
    {
        Gate::authorize('updateDeliveryDate', $solicitud);

        return $this->guardar($solicitud, $request->validated(), 'La fecha de entrega acordada fue actualizada.');
    }

    /**
     * Registrar entrega: Aprobada → Adopción concretada. PostgreSQL adopta la mascota, cierra las demás
     * solicitudes de la mascota, cancela las demás del adoptante (salvo «Permitir más de una adopción») y
     * crea el seguimiento.
     */
    public function deliver(RegistrarEntregaRequest $request, SolicitudAdopcion $solicitud): RedirectResponse
    {
        Gate::authorize('deliver', $solicitud);

        $datos = $request->validated();
        $fecha = Carbon::createFromFormat('Y-m-d', $datos['fecha_entrega'])->startOfDay();

        return $this->guardar($solicitud, [
            'estado' => 'aceptada',
            'compromiso_firmado' => true,
            // Entrega de hoy: la hora real. Entrega de un día anterior: el inicio de ese día.
            'entregada_at' => $fecha->isToday() ? now() : $fecha,
            ...(filled($datos['comentarios_admin'] ?? null) ? ['comentarios_admin' => $datos['comentarios_admin']] : []),
        ], fn (SolicitudAdopcion $s) => "¡Adopción concretada! {$s->mascota->nombre} ya tiene familia y se creó su seguimiento.");
    }

    /** Aprobada → Entrega no concretada (motivo obligatorio). La mascota vuelve a estar disponible. */
    public function markNotDelivered(EntregaNoConcretadaRequest $request, SolicitudAdopcion $solicitud): RedirectResponse
    {
        Gate::authorize('markNotDelivered', $solicitud);

        return $this->guardar($solicitud, [
            'estado' => 'no_concretada',
            'motivo_no_concretada' => $request->validated('motivo_no_concretada'),
        ], fn (SolicitudAdopcion $s) => "Se registró que la entrega no se concretó. {$s->mascota->nombre} vuelve a estar disponible.");
    }

    public function reject(DecideSolicitudAdopcionRequest $request, SolicitudAdopcion $solicitud): RedirectResponse
    {
        Gate::authorize('reject', $solicitud);

        return $this->guardar($solicitud, [
            'estado' => 'rechazada',
            'comentarios_admin' => $request->validated('comentarios_admin'),
        ], 'La solicitud de adopción fue rechazada.');
    }

    /**
     * Guarda los cambios en una transacción. Si PostgreSQL rechaza la operación (sus reglas o un cambio
     * simultáneo), no se guarda nada y se muestra su mensaje.
     *
     * @param  string|\Closure(SolicitudAdopcion): string  $mensaje
     */
    private function guardar(SolicitudAdopcion $solicitud, array $cambios, string|\Closure $mensaje): RedirectResponse
    {
        try {
            DB::transaction(fn () => $solicitud->forceFill($cambios)->save());
        } catch (QueryException $exception) {
            $error = ErrorBaseDatos::mensaje($exception) ?? throw $exception;

            return to_route('admin.solicitudes-adopcion.show', $solicitud)->withErrors(['solicitud' => $error]);
        }

        $solicitud->refresh()->load('mascota');

        return to_route('admin.solicitudes-adopcion.show', $solicitud)
            ->with('status', is_string($mensaje) ? $mensaje : $mensaje($solicitud));
    }
}
