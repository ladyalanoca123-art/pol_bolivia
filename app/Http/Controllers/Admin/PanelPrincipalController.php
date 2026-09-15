<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Incidencia;
use App\Models\Mascota;
use App\Models\PostulacionVoluntariado;
use App\Models\SolicitudAdopcion;
use App\Models\User;
use App\Models\Voluntario;
use Illuminate\View\View;

class PanelPrincipalController extends Controller
{
    public function index(): View
    {
        $indicadores = [
            'mascotas' => Mascota::count(),
            'solicitudesPendientes' => SolicitudAdopcion::where('estado', 'pendiente')->count(),
            'adopcionesAprobadas' => SolicitudAdopcion::where('estado', 'aceptada')->count(),
            'usuarios' => User::count(),
            'voluntariosActivos' => Voluntario::where('estado', 'activo')->count(),
            'postulacionesPendientes' => PostulacionVoluntariado::where('estado', 'pendiente')->count(),
            'incidenciasPendientes' => Incidencia::where('estado', 'pendiente')->count(),
        ];

        return view('admin.dashboard', ['indicadores' => $indicadores]);
    }
}
