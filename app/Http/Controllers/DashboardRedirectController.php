<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class DashboardRedirectController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        if (auth()->user()->estado && auth()->user()->hasRole('administrador')) {
            return to_route('admin.dashboard');
        }

        // Ciudadano y voluntario: su página de inicio, «Mi espacio».
        if (auth()->user()->hasRole('ciudadano', 'voluntario')) {
            return to_route('inicio');
        }

        return to_route('profile.edit');
    }
}
