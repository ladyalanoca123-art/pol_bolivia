<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterMascotasRequest;
use App\Http\Requests\Admin\RegisterMascotaRequest;
use App\Models\Mascota;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MascotaController extends Controller
{
    public function create(): View
    {
        return view('admin.mascotas.create');
    }

    public function index(FilterMascotasRequest $request): View
    {
        $filters = $request->validated();

        $mascotas = Mascota::query()
            ->with([
                'fotos' => fn ($query) => $query
                    ->where('tipo', 'foto')
                    ->where('es_principal', true),
            ])
            ->when(
                $filters['buscar'] ?? null,
                fn ($query, string $search) => $query->where('nombre', 'ilike', "%{$search}%")
            )
            ->when($filters['especie'] ?? null, fn ($query, string $species) => $query->where('especie', $species))
            ->when($filters['sexo'] ?? null, fn ($query, string $sex) => $query->where('sexo', $sex))
            ->when($filters['tamano'] ?? null, fn ($query, string $size) => $query->where('tamano', $size))
            ->when($filters['estado'] ?? null, fn ($query, string $status) => $query->where('estado', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.mascotas.index', compact('mascotas', 'filters'));
    }

    public function store(RegisterMascotaRequest $request): RedirectResponse
    {
        Mascota::create([
            ...$request->validated(),
            'estado' => 'borrador',
            'registrado_por' => auth()->id(),
        ]);

        return to_route('admin.mascotas.index')
            ->with('status', 'La mascota fue registrada como borrador.');
    }
}
