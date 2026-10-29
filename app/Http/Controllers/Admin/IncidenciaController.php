<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Incidencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class IncidenciaController extends Controller
{
    public function progress(Incidencia $incidencia): RedirectResponse
    {
        Gate::authorize('progress', $incidencia);
        $incidencia->forceFill(['estado' => 'en_proceso'])->save();

        return back()->with('status', 'La incidencia está en proceso.');
    }
}
