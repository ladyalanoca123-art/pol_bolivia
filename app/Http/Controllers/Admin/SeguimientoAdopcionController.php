<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seguimientos\AssignVolunteerRequest;
use App\Models\SeguimientoAdopcion;
use App\Models\User;
use App\Models\VisitaSeguimiento;
use App\Support\ErrorBaseDatos;
use App\Support\FechasSeguimientoAdopcion;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SeguimientoAdopcionController extends Controller
{
    public function index(): View
    {
        $seguimientos = SeguimientoAdopcion::query()->with(['solicitud.mascota', 'solicitud.solicitante', 'voluntario'])->latest()->paginate(15);

        return view('admin.seguimientos.index', compact('seguimientos'));
    }

    public function show(SeguimientoAdopcion $seguimiento): View
    {
        $seguimiento->load(['solicitud.mascota', 'solicitud.solicitante', 'voluntario', 'visitas', 'incidencias']);
        $fechasSugeridas = $seguimiento->solicitud->entregada_at
            ? FechasSeguimientoAdopcion::sugeridas($seguimiento->solicitud->entregada_at)
            : [];
        // El adoptante no puede ser el voluntario de su propio seguimiento (conflicto de interés).
        $voluntarios = User::query()->where('rol', 'voluntario')->where('estado', true)
            ->whereKeyNot($seguimiento->solicitud->solicitante_id)
            ->orderBy('nombres')->get();

        return view('admin.seguimientos.show', compact('seguimiento', 'voluntarios', 'fechasSugeridas'));
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

    public function assign(AssignVolunteerRequest $request, SeguimientoAdopcion $seguimiento): RedirectResponse
    {
        Gate::authorize('assign', $seguimiento);
        $seguimiento->forceFill([
            'voluntario_id' => $request->integer('voluntario_id'),
            'estado' => 'activo',
            'fecha_inicio' => now(),
        ])->save();

        return back()->with('status', 'El voluntario fue asignado y el seguimiento fue activado.');
    }

    public function close(SeguimientoAdopcion $seguimiento): RedirectResponse
    {
        Gate::authorize('close', $seguimiento);

        if ($pendientes = $seguimiento->pendientesParaCerrar()) {
            return back()->withErrors(['cierre' => 'Todavía no se puede cerrar: '.implode(' ', $pendientes)]);
        }

        try {
            $seguimiento->forceFill(['estado' => 'cerrado', 'fecha_cierre' => now()])->save();
        } catch (QueryException $exception) {
            // Algo cambió entre la revisión y el cierre (p. ej. el voluntario reportó una incidencia): PostgreSQL lo
            // impide y se muestra su motivo en lugar de la página de error.
            $error = ErrorBaseDatos::mensaje($exception) ?? throw $exception;

            return back()->withErrors(['cierre' => $error]);
        }

        return back()->with('status', 'El seguimiento fue cerrado.');
    }
}
