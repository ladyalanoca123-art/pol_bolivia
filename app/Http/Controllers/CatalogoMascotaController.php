<?php

namespace App\Http\Controllers;

use App\Models\Mascota;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogoMascotaController extends Controller
{
    public function home(): View
    {
        $mascotas = $this->mascotasDisponibles()->limit(7)->get();

        return view('welcome', compact('mascotas'));
    }

    public function index(Request $request): View
    {
        $especie = $request->query('especie');
        $especie = in_array($especie, ['perro', 'gato'], true) ? $especie : null;

        $mascotas = $this->mascotasDisponibles($especie)->paginate(12)->withQueryString();

        return view('mascotas-publicas.index', compact('mascotas', 'especie'));
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
