<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\User;
use App\Support\PresentadorAuditoria;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Consulta del historial de auditoría (solo administrador, ver routes/web.php).
 *
 * auditorias.created_at guarda el instante en UTC sin zona (App\Models\Auditoria): los límites de fecha se
 * calculan en la zona de la aplicación y se enlazan en UTC.
 */
class AuditoriaController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'modelo' => ['nullable', 'string', 'max:100'],
            'accion' => ['nullable', Rule::in(array_keys(PresentadorAuditoria::ACCIONES))],
            'usuario' => ['nullable', 'integer'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ], [
            'desde.date_format' => 'Indica una fecha válida.',
            'hasta.date_format' => 'Indica una fecha válida.',
            'hasta.after_or_equal' => 'La fecha «hasta» no puede ser anterior a «desde».',
        ]);

        $zona = config('app.timezone');

        $auditorias = Auditoria::query()
            ->with('usuario:id,nombres,apellidos,email')
            ->when($filtros['modelo'] ?? null, fn (Builder $query, string $modelo) => $query->where('modelo', $modelo))
            ->when($filtros['accion'] ?? null, fn (Builder $query, string $accion) => $query->where('accion', $accion))
            ->when($filtros['usuario'] ?? null, fn (Builder $query, int $usuario) => $query->where('usuario_id', $usuario))
            ->when($filtros['desde'] ?? null, fn (Builder $query, string $desde) => $query->where('created_at', '>=', CarbonImmutable::parse($desde, $zona)->startOfDay()->utc()))
            ->when($filtros['hasta'] ?? null, fn (Builder $query, string $hasta) => $query->where('created_at', '<', CarbonImmutable::parse($hasta, $zona)->addDay()->startOfDay()->utc()))
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $modelos = Auditoria::query()->distinct()->orderBy('modelo')->pluck('modelo');
        $usuarios = User::query()
            ->whereIn('id', Auditoria::query()->select('usuario_id')->whereNotNull('usuario_id')->distinct())
            ->orderBy('nombres')->orderBy('apellidos')
            ->get(['id', 'nombres', 'apellidos']);

        return view('admin.auditoria.index', [
            'auditorias' => $auditorias,
            'modelos' => $modelos,
            'usuarios' => $usuarios,
            'filtros' => $filtros,
            'resumen' => $this->resumen($zona),
        ]);
    }

    /** Actividad de hoy y de los últimos 7 días (incluido hoy), sin filtros. */
    private function resumen(string $zona): array
    {
        $hoy = CarbonImmutable::now($zona)->startOfDay();
        $semana = $hoy->subDays(6);
        $desde = fn (CarbonImmutable $fecha) => Auditoria::query()->where('created_at', '>=', $fecha->utc());

        return [
            'hoy' => $desde($hoy)->count(),
            'semana' => $desde($semana)->count(),
            'personas' => $desde($semana)->whereNotNull('usuario_id')->distinct()->count('usuario_id'),
            'eliminaciones' => $desde($semana)->where('accion', 'eliminado')->count(),
        ];
    }
}
