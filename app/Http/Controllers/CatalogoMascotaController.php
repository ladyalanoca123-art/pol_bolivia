<?php

namespace App\Http\Controllers;

use App\Models\Mascota;
use App\Models\SolicitudAdopcion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogoMascotaController extends Controller
{
    public function home(): View
    {
        $esperanHogar = $this->esperanHogar();
        // Las de la fila de arriba no se repiten en la cuadrícula.
        $mascotas = $this->mascotasDisponibles()->whereNotIn('id', $esperanHogar->modelKeys())->limit(7)->get();

        return view('welcome', compact('mascotas', 'esperanHogar'));
    }

    public function index(Request $request): View
    {
        $especie = $request->query('especie');
        $especie = in_array($especie, ['perro', 'gato'], true) ? $especie : null;

        $esperanHogar = $this->esperanHogar($especie);
        $mascotas = $this->mascotasDisponibles($especie)
            ->whereNotIn('id', $esperanHogar->modelKeys())
            ->paginate(12)->withQueryString();

        return view('mascotas-publicas.index', compact('mascotas', 'especie', 'esperanHogar'));
    }

    /**
     * Mascotas disponibles que llevan DIAS_ESPERANDO_HOGAR días o más en el sistema y no tienen ninguna
     * solicitud en trámite: se promocionan en una fila aparte, primero las que más esperan.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Mascota>
     */
    private function esperanHogar(?string $especie = null)
    {
        return $this->mascotasDisponibles($especie)
            ->where('created_at', '<=', now()->subDays(Mascota::DIAS_ESPERANDO_HOGAR))
            ->whereDoesntHave('solicitudesAdopcion', fn ($query) => $query->whereIn('estado', SolicitudAdopcion::ESTADOS_ACTIVOS))
            ->reorder()->orderBy('created_at')->orderBy('id')
            ->limit(12)
            ->get();
    }

    public function show(Mascota $mascota): View
    {
        abort_unless($mascota->estado === 'disponible', 404);

        $mascota->load(['fotos' => fn ($query) => $query->orderByDesc('es_principal')->orderBy('orden')]);

        return view('mascotas-publicas.show', compact('mascota'));
    }

    private function mascotasDisponibles(?string $especie = null): Builder
    {
        $query = Mascota::query()
            ->where('estado', 'disponible')
            ->with(['fotos' => fn ($query) => $query
                ->where('tipo', 'foto')
                ->orderByDesc('es_principal')
                ->orderBy('orden')])
            ->orderBy('nombre');

        return $especie ? $query->where('especie', $especie) : $query;
    }
}
