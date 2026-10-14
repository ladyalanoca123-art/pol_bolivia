<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consulta del historial de auditoría (solo administrador, ver routes/web.php).
 */
class AuditoriaController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'modelo' => ['nullable', 'string', 'max:100'],
            'accion' => ['nullable', 'in:creado,actualizado,eliminado'],
        ]);

        $auditorias = Auditoria::query()
            ->with('usuario:id,nombres,apellidos,email')
            ->when($filtros['modelo'] ?? null, fn ($query, string $modelo) => $query->where('modelo', $modelo))
            ->when($filtros['accion'] ?? null, fn ($query, string $accion) => $query->where('accion', $accion))
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $modelos = Auditoria::query()->distinct()->orderBy('modelo')->pluck('modelo');

        return view('admin.auditoria.index', compact('auditorias', 'modelos', 'filtros'));
    }
}
