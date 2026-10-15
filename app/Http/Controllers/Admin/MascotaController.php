<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterMascotasRequest;
use App\Http\Requests\Admin\RegisterMascotaRequest;
use App\Http\Requests\Admin\StoreFotoMascotaRequest;
use App\Http\Requests\Admin\UpdateMascotaRequest;
use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Services\ImagenNoProcesada;
use App\Services\ImagenSegura;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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

    public function store(RegisterMascotaRequest $request, ImagenSegura $imagenSegura): RedirectResponse
    {
        $data = $request->validated();
        $photo = $data['foto_principal'];
        unset($data['foto_principal']);
        $path = null;

        try {
            DB::transaction(function () use ($data, $photo, $imagenSegura, &$path): void {
                $mascota = Mascota::create([
                    ...$data,
                    'estado' => 'disponible',
                    'registrado_por' => auth()->id(),
                ]);

                // Guarda la foto sin metadatos EXIF (ver app/Services/ImagenSegura.php).
                $path = $imagenSegura->guardar($photo, "mascotas/{$mascota->id}");

                $mascota->fotos()->create([
                    'tipo' => 'foto',
                    'url' => $path,
                    'es_principal' => true,
                    'orden' => 1,
                ]);
            });
        } catch (\Throwable $exception) {
            if ($path !== null) {
                $imagenSegura->eliminar($path);
            }

            // La transacción ya se deshizo: la mascota no queda registrada sin su foto.
            if ($exception instanceof ImagenNoProcesada) {
                return back()->withInput()->withErrors(['foto_principal' => $exception->getMessage()]);
            }

            throw $exception;
        }

        return to_route('admin.mascotas.index')
            ->with('status', 'La mascota fue registrada correctamente.');
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

    public function edit(Mascota $mascota): View
    {
        return view('admin.mascotas.edit', compact('mascota'));
    }

    public function update(UpdateMascotaRequest $request, Mascota $mascota): RedirectResponse
    {
        $mascota->update($request->validated());

        return to_route('admin.mascotas.show', $mascota)
            ->with('status', 'La información de la mascota fue actualizada.');
    }

    public function storePhoto(StoreFotoMascotaRequest $request, Mascota $mascota, ImagenSegura $imagenSegura): RedirectResponse
    {
        // Guarda la foto sin metadatos EXIF, reducida y con su miniatura (ver app/Services/ImagenSegura.php).
        try {
            $path = $imagenSegura->guardar($request->file('foto'), "mascotas/{$mascota->id}");
        } catch (ImagenNoProcesada $exception) {
            return to_route('admin.mascotas.show', $mascota)->withErrors(['foto' => $exception->getMessage()]);
        }

        try {
            $agregada = DB::transaction(function () use ($mascota, $path): bool {
                Mascota::query()->lockForUpdate()->findOrFail($mascota->id);

                // Con la mascota bloqueada: otra subida simultánea pudo completar el límite.
                if ($mascota->fotos()->where('tipo', 'foto')->count() >= FotoMascota::MAXIMO_POR_MASCOTA) {
                    return false;
                }

                $order = $mascota->fotos()
                    ->where('tipo', 'foto')
                    ->max('orden') + 1;

                $mascota->fotos()->create([
                    'tipo' => 'foto',
                    'url' => $path,
                    'es_principal' => false,
                    'orden' => $order,
                ]);

                return true;
            });
        } catch (\Throwable $exception) {
            $imagenSegura->eliminar($path);

            throw $exception;
        }

        if (! $agregada) {
            $imagenSegura->eliminar($path);

            return to_route('admin.mascotas.show', $mascota)->withErrors(['foto' => FotoMascota::MENSAJE_LIMITE]);
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

    /**
     * Elimina una foto sin dejar a una mascota disponible sin foto principal, como exige PostgreSQL
     * (trigger_validar_multimedia_publicada): la única foto de una mascota disponible no se elimina, y si
     * se elimina la principal pasa a serlo la siguiente por orden. El archivo se borra tras confirmar.
     */
    public function destroyPhoto(Mascota $mascota, FotoMascota $foto, ImagenSegura $imagenSegura): RedirectResponse
    {
        $foto = $this->findPetPhoto($mascota, $foto);

        $resultado = DB::transaction(function () use ($mascota, $foto): array {
            $estado = Mascota::query()->lockForUpdate()->findOrFail($mascota->id)->estado;
            $fotos = $mascota->fotos()->where('tipo', 'foto')->orderBy('orden')->get();
            $foto = $fotos->firstWhere('id', $foto->id) ?? abort(404);

            if ($fotos->count() === 1 && $estado === 'disponible') {
                return ['eliminada' => false];
            }

            $nuevaPrincipal = $foto->es_principal ? $fotos->first(fn (FotoMascota $f) => $f->id !== $foto->id) : null;

            $foto->delete();
            $nuevaPrincipal?->forceFill(['es_principal' => true])->save();

            return ['eliminada' => true, 'ruta' => $foto->url, 'nuevaPrincipal' => $nuevaPrincipal];
        });

        if (! $resultado['eliminada']) {
            return to_route('admin.mascotas.show', $mascota)
                ->withErrors(['fotos' => 'No se puede eliminar la única foto de una mascota disponible. Agrega otra foto antes de eliminar esta.']);
        }

        // Borra la imagen y su miniatura.
        $imagenSegura->eliminar($resultado['ruta']);

        return to_route('admin.mascotas.show', $mascota)->with('status', $resultado['nuevaPrincipal']
            ? "La foto fue eliminada. La foto en la posición {$resultado['nuevaPrincipal']->orden} pasó a ser la principal."
            : 'La foto fue eliminada correctamente.');
    }

    private function findPetPhoto(Mascota $mascota, FotoMascota $foto): FotoMascota
    {
        return $mascota->fotos()
            ->whereKey($foto->id)
            ->where('tipo', 'foto')
            ->firstOrFail();
    }
}
