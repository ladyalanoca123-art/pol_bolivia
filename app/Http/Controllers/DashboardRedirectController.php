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

        return to_route('profile.edit');
    }
}
