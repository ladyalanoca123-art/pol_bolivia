<?php

namespace App\Http\Controllers;

use App\Models\Mascota;
use App\Models\SolicitudAdopcion;
use App\Models\VisitaSeguimiento;
use Illuminate\View\View;

/**
 * «Mi espacio»: página de inicio del ciudadano y del voluntario al iniciar sesión.
 * Resume sus solicitudes, su postulación y, para el voluntario, sus seguimientos y próximas visitas.
 * Solo muestra datos de la propia persona; las acciones siguen en sus pantallas (con sus políticas).
 */
class MiEspacioController extends Controller
{
    public function __invoke(): View
    {
        $usuario = auth()->user();
        $esVoluntario = $usuario->hasRole('voluntario');
        $fotos = fn ($query) => $query->where('tipo', 'foto')->orderByDesc('es_principal')->orderBy('orden');

        $solicitudes = $usuario->solicitudesAdopcion()
            ->with(['mascota.fotos' => $fotos])
            ->latest()->limit(3)->get();
        $solicitudesActivas = $usuario->solicitudesAdopcion()->whereIn('estado', SolicitudAdopcion::ESTADOS_ACTIVOS)->count();
        $postulacion = $usuario->postulacionesVoluntariado()->latest()->first();

        $datos = compact('esVoluntario', 'solicitudes', 'solicitudesActivas', 'postulacion');

        if ($esVoluntario) {
            $seguimientos = $usuario->seguimientosAsignados()->where('estado', 'activo')
                ->with(['solicitud.mascota.fotos' => $fotos, 'solicitud.solicitante:id,nombres,apellidos'])
                ->withCount(['visitas as visitas_realizadas' => fn ($q) => $q->where('estado', 'realizada')])
                ->latest()->get();
            $proximasVisitas = VisitaSeguimiento::query()
                ->where('voluntario_id', $usuario->id)->where('estado', 'programada')
                ->with('seguimiento.solicitud.mascota:id,nombre')
                ->orderBy('fecha_programada')->limit(5)->get();

            return view('mi-espacio', [...$datos, 'seguimientos' => $seguimientos, 'proximasVisitas' => $proximasVisitas]);
        }

        // Ciudadano: algunas mascotas que esperan hogar, para invitar a conocerlas.
        $mascotas = Mascota::query()->where('estado', 'disponible')
            ->with(['fotos' => $fotos])->latest()->limit(4)->get();
        $mascotasDisponibles = Mascota::query()->where('estado', 'disponible')->count();

        return view('mi-espacio', [...$datos, 'mascotas' => $mascotas, 'mascotasDisponibles' => $mascotasDisponibles]);
    }
}
