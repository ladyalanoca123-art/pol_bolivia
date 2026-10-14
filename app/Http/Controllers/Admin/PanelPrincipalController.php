<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PanelAdministrador;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PanelPrincipalController extends Controller
{
    public function index(Request $request, PanelAdministrador $panel): View
    {
        return view('admin.dashboard', [
            'indicadores' => $panel->indicadoresActuales(),
            'panel' => $panel->datos($request->user()),
        ]);
    }
}
