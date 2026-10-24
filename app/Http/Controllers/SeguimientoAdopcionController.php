<?php

namespace App\Http\Controllers;

use App\Http\Requests\Seguimientos\AttendIncidenciaRequest;
use App\Http\Requests\Seguimientos\CompleteVisitaRequest;
use App\Http\Requests\Seguimientos\StoreIncidenciaRequest;
use App\Http\Requests\Seguimientos\StoreVisitaRequest;
use App\Models\Incidencia;
use App\Models\SeguimientoAdopcion;
use App\Models\VisitaSeguimiento;
use App\Support\FechasSeguimientoAdopcion;
use App\Support\CuestionarioVisitaSeguimiento;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class SeguimientoAdopcionController extends Controller
{
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

            if ($visitas->count() === 1 && $visitas->first()->estado !== 'realizada') {
                throw ValidationException::withMessages(['fecha_programada' => 'Primero completa la visita 1 para habilitar la visita 2.']);
            }

            $numero = $visitas->count() + 1;
            $seguimientoBloqueado->visitas()->create([...$datos, 'voluntario_id' => auth()->id(), 'estado' => 'programada']);

            return $numero;
        });

        $entrega = $seguimiento->solicitud()->first()?->entregada_at;
        $fechas = $entrega ? FechasSeguimientoAdopcion::sugeridas($entrega) : [];
        $margen = $fechas[$numeroVisita] ?? null;

        $respuesta = back()->with('status', 'La visita fue programada.');
        if ($margen && ! FechasSeguimientoAdopcion::dentroDelMargen($datos['fecha_programada'], $margen)) {
            $respuesta->with('warning', "La fecha quedó fuera del margen recomendado para la visita {$numeroVisita} (".
                CarbonImmutable::parse($margen['desde'])->format('d/m/Y').' al '.CarbonImmutable::parse($margen['hasta'])->format('d/m/Y').').');
        }

        return $respuesta;
    }

    public function completeVisit(CompleteVisitaRequest $request, SeguimientoAdopcion $seguimiento, VisitaSeguimiento $visita): RedirectResponse
    {
        Gate::authorize('work', $seguimiento);
        abort_unless($visita->seguimiento_id === $seguimiento->id && $visita->voluntario_id === auth()->id() && $visita->estado === 'programada', 404);
        $datos = $request->validated();
        $numeroVisita = CuestionarioVisitaSeguimiento::numero($visita);
        if ($numeroVisita === 2 && $seguimiento->visitas()->orderBy('id')->first()?->estado !== 'realizada') {
            throw ValidationException::withMessages(['fecha_realizada' => 'Primero registra como realizada la visita 1.']);
        }
        $resumen = CuestionarioVisitaSeguimiento::resumen($numeroVisita, $datos['respuestas']);
        $observaciones = "Cuestionario de visita {$numeroVisita}\n{$resumen}";
        if (filled($datos['observaciones'] ?? null)) {
            $observaciones .= "\n\nObservaciones adicionales:\n".trim($datos['observaciones']);
        }

        $rutaFoto = $request->hasFile('foto')
            ? $request->file('foto')->store('seguimientos/visitas', 'local')
            : null;
        if ($rutaFoto === false) {
            return back()->withErrors(['foto' => 'No se pudo guardar la foto. Inténtalo nuevamente.'])->withInput();
        }

        try {
            $visita->forceFill([
                'fecha_realizada' => $datos['fecha_realizada'],
                'estado_salud' => CuestionarioVisitaSeguimiento::estadoSalud($numeroVisita, $datos['respuestas']),
                'observaciones' => $observaciones,
                'foto_url' => $rutaFoto ?? $visita->foto_url,
                'estado' => 'realizada',
            ])->save();
        } catch (Throwable $exception) {
            if ($rutaFoto !== null) {
                Storage::disk('local')->delete($rutaFoto);
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
        if (isset($data['visita_id']) && ! $seguimiento->visitas()->whereKey($data['visita_id'])->exists()) abort(404);
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
}
