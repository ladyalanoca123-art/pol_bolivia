<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterMascotasRequest;
use App\Http\Requests\Admin\RegisterMascotaRequest;
use App\Http\Requests\Admin\StoreFotoMascotaRequest;
use App\Models\FotoMascota;
use App\Models\Mascota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    public function show(Mascota $mascota): View
    {
        $mascota->load([
            'registradoPor',
            'fotos' => fn ($query) => $query
                ->where('tipo', 'foto')
                ->orderBy('orden'),
        ]);

        return view('admin.mascotas.show', [
            'mascota' => $mascota,
            'fotoPrincipal' => $mascota->fotos->firstWhere('es_principal', true),
        ]);
    }

    public function storePhoto(StoreFotoMascotaRequest $request, Mascota $mascota): RedirectResponse
    {
        $path = $request->file('foto')->store("mascotas/{$mascota->id}", 'public');

        try {
            DB::transaction(function () use ($mascota, $path): void {
                Mascota::query()->lockForUpdate()->findOrFail($mascota->id);

                $order = $mascota->fotos()
                    ->where('tipo', 'foto')
                    ->max('orden') + 1;

                $mascota->fotos()->create([
                    'tipo' => 'foto',
                    'url' => $path,
                    'es_principal' => false,
                    'orden' => $order,
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }

        return to_route('admin.mascotas.show', $mascota)
            ->with('status', 'La foto fue agregada correctamente.');
    }

    public function setPrincipalPhoto(Mascota $mascota, FotoMascota $foto): RedirectResponse
    {
        $foto = $this->findPetPhoto($mascota, $foto);

        DB::transaction(function () use ($mascota, $foto): void {
            $mascota->fotos()
                ->where('tipo', 'foto')
                ->whereKeyNot($foto->id)
                ->update(['es_principal' => false]);

            $foto->forceFill(['es_principal' => true])->save();
        });

        return to_route('admin.mascotas.show', $mascota)
            ->with('status', 'La foto principal fue actualizada.');
    }

    public function destroyPhoto(Mascota $mascota, FotoMascota $foto): RedirectResponse
    {
        $foto = $this->findPetPhoto($mascota, $foto);
        $path = $foto->url;

        DB::transaction(fn () => $foto->delete());

        Storage::disk('public')->delete($path);

        return to_route('admin.mascotas.show', $mascota)
            ->with('status', 'La foto fue eliminada correctamente.');
    }

    private function findPetPhoto(Mascota $mascota, FotoMascota $foto): FotoMascota
    {
        return $mascota->fotos()
            ->whereKey($foto->id)
            ->where('tipo', 'foto')
            ->firstOrFail();
    }
}
