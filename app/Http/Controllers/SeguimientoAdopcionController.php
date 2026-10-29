<?php

namespace App\Http\Controllers;

use App\Http\Requests\Seguimientos\AttendIncidenciaRequest;
use App\Http\Requests\Seguimientos\CompleteVisitaRequest;
use App\Http\Requests\Seguimientos\MarkNoShowVisitaRequest;
use App\Http\Requests\Seguimientos\RescheduleVisitaRequest;
use App\Http\Requests\Seguimientos\StoreIncidenciaRequest;
use App\Http\Requests\Seguimientos\StoreVisitaRequest;
use App\Models\Incidencia;
use App\Models\SeguimientoAdopcion;
use App\Models\VisitaSeguimiento;
use App\Services\ImagenNoProcesada;
use App\Services\ImagenSegura;
use App\Support\CuestionarioVisitaSeguimiento;
use App\Support\FechasSeguimientoAdopcion;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Seguimiento post-adopción del voluntario asignado: 2 visitas (PostgreSQL no admite más, validar_limite_visitas).
 * Una visita programada se puede reprogramar o marcar «No asistió»; la visita 2 se habilita cuando la visita 1
 * terminó (realizada o no asistió), para que el seguimiento nunca quede trabado.
 */
class SeguimientoAdopcionController extends Controller
{
    /** Estados con los que una visita ya terminó. */
    public const VISITA_TERMINADA = ['realizada', 'no_asistio'];

    public function index(): View
    {
        $seguimientos = SeguimientoAdopcion::query()->where('voluntario_id', auth()->id())->with('solicitud.mascota')->latest()->paginate(15);

        return view('seguimientos.index', compact('seguimientos'));
    }

    public function show(SeguimientoAdopcion $seguimiento): View
    {
        Gate::authorize('view', $seguimiento);
        $seguimiento->load(['solicitud.mascota', 'solicitud.solicitante', 'visitas', 'incidencias']);
        $fechasSugeridas = $seguimiento->solicitud->entregada_at
            ? FechasSeguimientoAdopcion::sugeridas($seguimiento->solicitud->entregada_at)
            : [];

        return view('seguimientos.show', compact('seguimiento', 'fechasSugeridas'));
    }

    public function storeVisit(StoreVisitaRequest $request, SeguimientoAdopcion $seguimiento): RedirectResponse
    {
        $datos = $request->validated();
        $numeroVisita = DB::transaction(function () use ($seguimiento, $datos): int {
            // Serializa la programación para impedir que dos envíos creen la visita 1 y 2 juntas.
            $seguimientoBloqueado = SeguimientoAdopcion::query()->whereKey($seguimiento->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('work', $seguimientoBloqueado);
            $visitas = $seguimientoBloqueado->visitas()->orderBy('id')->lockForUpdate()->get();

            if ($visitas->count() >= 2) {
                throw ValidationException::withMessages(['fecha_programada' => 'Las dos visitas de seguimiento ya fueron programadas.']);
            }

            if ($visitas->count() === 1 && ! in_array($visitas->first()->estado, self::VISITA_TERMINADA, true)) {
                throw ValidationException::withMessages(['fecha_programada' => 'Primero registra la visita 1 (realizada o no asistió) para habilitar la visita 2. Si cambió el día, reprográmala.']);
            }

            $seguimientoBloqueado->visitas()->create([...$datos, 'voluntario_id' => auth()->id(), 'estado' => 'programada']);

            return $visitas->count() + 1;
        });

        return $this->conAvisoDeMargen(back()->with('status', 'La visita fue programada.'), $seguimiento, $numeroVisita, $datos['fecha_programada']);
    }

    /** Cambia el día de una visita programada (p. ej., la familia no estaba). No crea otra visita. */
    public function rescheduleVisit(RescheduleVisitaRequest $request, SeguimientoAdopcion $seguimiento, VisitaSeguimiento $visita): RedirectResponse
    {
        $this->exigirVisitaProgramadaPropia($seguimiento, $visita);
        $fecha = $request->validated('fecha_programada');

        $visita->forceFill(['fecha_programada' => $fecha])->save();

        return $this->conAvisoDeMargen(back()->with('status', 'La visita fue reprogramada.'), $seguimiento, CuestionarioVisitaSeguimiento::numero($visita), $fecha);
    }

    /** La familia no recibió la visita: queda registrada como «No asistió» y se habilita la siguiente. */
    public function markNoShow(MarkNoShowVisitaRequest $request, SeguimientoAdopcion $seguimiento, VisitaSeguimiento $visita): RedirectResponse
    {
        $this->exigirVisitaProgramadaPropia($seguimiento, $visita);
        $motivo = trim((string) $request->validated('observaciones'));

        $visita->forceFill([
            'estado' => 'no_asistio',
            'observaciones' => $motivo !== '' ? 'No asistió: '.$motivo : 'No asistió.',
        ])->save();

        return back()->with('status', 'La visita quedó registrada como «No asistió». Si perdiste el contacto con la familia, reporta una incidencia.');
    }

    public function completeVisit(CompleteVisitaRequest $request, SeguimientoAdopcion $seguimiento, VisitaSeguimiento $visita): RedirectResponse
    {
        $this->exigirVisitaProgramadaPropia($seguimiento, $visita);
        $datos = $request->validated();
        $numeroVisita = CuestionarioVisitaSeguimiento::numero($visita);
        if ($numeroVisita === 2 && ! in_array($seguimiento->visitas()->orderBy('id')->first()?->estado, self::VISITA_TERMINADA, true)) {
            throw ValidationException::withMessages(['fecha_realizada' => 'Primero registra la visita 1.']);
        }
        $resumen = CuestionarioVisitaSeguimiento::resumen($numeroVisita, $datos['respuestas']);
        $observaciones = "Cuestionario de visita {$numeroVisita}\n{$resumen}";
        if (filled($datos['observaciones'] ?? null)) {
            $observaciones .= "\n\nObservaciones adicionales:\n".trim($datos['observaciones']);
        }

        // La foto se vuelve a codificar con GD (ImagenSegura): se descartan los metadatos EXIF, entre ellos la
        // ubicación GPS del hogar del adoptante. Se guarda en el disco privado y no se usa miniatura.
        $rutaFoto = null;
        if ($request->hasFile('foto')) {
            try {
                $rutaFoto = app(ImagenSegura::class)->guardar($request->file('foto'), 'seguimientos/visitas', 'local');
                Storage::disk('local')->delete(ImagenSegura::rutaMiniatura($rutaFoto));
            } catch (ImagenNoProcesada $exception) {
                return back()->withErrors(['foto' => $exception->getMessage()])->withInput();
            }
        }

        // Visita adelantada: PostgreSQL exige que la fecha realizada no sea anterior a la programada
        // (visitas_cronologia_valida), así que la fecha programada pasa a ser la del día en que se hizo.
        $realizada = $datos['fecha_realizada'];
        $programada = $visita->fecha_programada->toDateString();

        try {
            $visita->forceFill([
                'fecha_programada' => min($programada, $realizada),
                'fecha_realizada' => $realizada,
                'estado_salud' => CuestionarioVisitaSeguimiento::estadoSalud($numeroVisita, $datos['respuestas']),
                'observaciones' => $observaciones,
                'foto_url' => $rutaFoto ?? $visita->foto_url,
                'estado' => 'realizada',
            ])->save();
        } catch (Throwable $exception) {
            if ($rutaFoto !== null) {
                app(ImagenSegura::class)->eliminar($rutaFoto, 'local');
            }

            throw $exception;
        }

        return back()->with('status', 'La visita fue registrada como realizada.');
    }

    public function visitPhoto(SeguimientoAdopcion $seguimiento, VisitaSeguimiento $visita)
    {
        Gate::authorize('view', $seguimiento);
        abort_unless($visita->seguimiento_id === $seguimiento->id, 404);
        abort_unless(
            $visita->foto_url
            && str_starts_with($visita->foto_url, 'seguimientos/visitas/')
            && Storage::disk('local')->exists($visita->foto_url),
            404
        );

        return Storage::disk('local')->response($visita->foto_url, null, [
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function storeIncident(StoreIncidenciaRequest $request, SeguimientoAdopcion $seguimiento): RedirectResponse
    {
        Gate::authorize('work', $seguimiento);
        $data = $request->validated();
        if (isset($data['visita_id']) && ! $seguimiento->visitas()->whereKey($data['visita_id'])->exists()) {
            abort(404);
        }
        $seguimiento->incidencias()->create([...$data, 'reportado_por' => auth()->id(), 'estado' => 'pendiente']);

        return back()->with('status', 'La incidencia fue registrada.');
    }

    public function attendIncident(AttendIncidenciaRequest $request, SeguimientoAdopcion $seguimiento, Incidencia $incidencia): RedirectResponse
    {
        abort_unless($incidencia->seguimiento_id === $seguimiento->id, 404);
        Gate::authorize('attend', $incidencia);
        $incidencia->forceFill([...$request->validated(), 'estado' => 'atendida', 'atendida_por' => auth()->id(), 'atendida_at' => now()])->save();

        return back()->with('status', 'La incidencia fue atendida.');
    }

    /** Solo el voluntario asignado, en un seguimiento activo, sobre una visita suya que sigue programada. */
    private function exigirVisitaProgramadaPropia(SeguimientoAdopcion $seguimiento, VisitaSeguimiento $visita): void
    {
        Gate::authorize('work', $seguimiento);
        abort_unless($visita->seguimiento_id === $seguimiento->id && $visita->voluntario_id === auth()->id() && $visita->estado === 'programada', 404);
    }

    /** Avisa (sin impedirlo) si la fecha queda fuera del margen recomendado para esa visita. */
    private function conAvisoDeMargen(RedirectResponse $respuesta, SeguimientoAdopcion $seguimiento, int $numeroVisita, string $fecha): RedirectResponse
    {
        $entrega = $seguimiento->solicitud()->first()?->entregada_at;
        $margen = $entrega ? (FechasSeguimientoAdopcion::sugeridas($entrega)[$numeroVisita] ?? null) : null;

        if ($margen && ! FechasSeguimientoAdopcion::dentroDelMargen($fecha, $margen)) {
            $respuesta->with('warning', "La fecha quedó fuera del margen recomendado para la visita {$numeroVisita} (".
                CarbonImmutable::parse($margen['desde'])->format('d/m/Y').' al '.CarbonImmutable::parse($margen['hasta'])->format('d/m/Y').').');
        }

        return $respuesta;
    }
}
