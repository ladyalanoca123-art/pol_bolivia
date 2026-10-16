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
use Illuminate\Database\QueryException;
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
        // La principal (orden 1) y las adicionales (orden 2, 3 y 4), con su campo para los errores.
        $photos = ['foto_principal' => $data['foto_principal']];

        foreach ($data['fotos_adicionales'] ?? [] as $index => $photo) {
            $photos["fotos_adicionales.{$index}"] = $photo;
        }

        unset($data['foto_principal'], $data['fotos_adicionales']);
        $paths = [];
        $field = 'foto_principal';

        try {
            DB::transaction(function () use ($data, $photos, $imagenSegura, &$paths, &$field): void {
                $mascota = Mascota::create([
                    ...$data,
                    'estado' => 'disponible',
                    'registrado_por' => auth()->id(),
                ]);

                // Guarda las fotos sin metadatos EXIF (ver app/Services/ImagenSegura.php) y después sus registros.
                foreach ($photos as $field => $photo) {
                    $paths[] = $imagenSegura->guardar($photo, "mascotas/{$mascota->id}");
                }

                foreach ($paths as $index => $path) {
                    $mascota->fotos()->create([
                        'tipo' => 'foto',
                        'url' => $path,
                        'es_principal' => $index === 0,
                        'orden' => $index + 1,
                    ]);
                }
            });
        } catch (\Throwable $exception) {
            // La transacción ya se deshizo: se borran solo los archivos de este registro.
            $this->deletePhotos($imagenSegura, $paths);

            if ($exception instanceof ImagenNoProcesada) {
                return back()->withInput()->withErrors([$field => $this->photoErrorPrefix($field).$exception->getMessage()]);
            }

            if ($exception instanceof QueryException) {
                report($exception);

                return back()->withInput()->withErrors(['foto_principal' => 'No se pudieron guardar las fotos. Inténtalo de nuevo.']);
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

    /**
     * Agrega una o varias fotos (nunca la principal). Primero guarda cada archivo; después crea todos los
     * registros en una transacción. Si algo falla no queda nada de esta subida: ni registros ni archivos.
     */
    public function storePhoto(StoreFotoMascotaRequest $request, Mascota $mascota, ImagenSegura $imagenSegura): RedirectResponse
    {
        $field = $request->campo();
        $photos = $field === 'foto' ? ['foto' => $request->file('foto')] : [];

        foreach ($field === 'fotos' ? $request->file('fotos') : [] as $index => $photo) {
            $photos["fotos.{$index}"] = $photo;
        }

        $paths = [];

        // Guarda las fotos sin metadatos EXIF, reducidas y con su miniatura (ver app/Services/ImagenSegura.php).
        try {
            foreach ($photos as $key => $photo) {
                $paths[] = $imagenSegura->guardar($photo, "mascotas/{$mascota->id}");
            }
        } catch (ImagenNoProcesada $exception) {
            $this->deletePhotos($imagenSegura, $paths);

            return to_route('admin.mascotas.show', $mascota)->withErrors([$field => $this->photoErrorPrefix($key).$exception->getMessage()]);
        }

        try {
            $freeSlots = DB::transaction(function () use ($mascota, $paths): ?int {
                Mascota::query()->lockForUpdate()->findOrFail($mascota->id);

                // Con la mascota bloqueada: otra subida simultánea pudo ocupar los espacios libres.
                $freeSlots = FotoMascota::MAXIMO_POR_MASCOTA - $mascota->fotos()->where('tipo', 'foto')->count();

                if (count($paths) > $freeSlots) {
                    return max(0, $freeSlots);
                }

                $order = $mascota->fotos()
                    ->where('tipo', 'foto')
                    ->max('orden');

                foreach ($paths as $path) {
                    $mascota->fotos()->create([
                        'tipo' => 'foto',
                        'url' => $path,
                        'es_principal' => false,
                        'orden' => ++$order,
                    ]);
                }

                return null;
            });
        } catch (QueryException $exception) {
            $this->deletePhotos($imagenSegura, $paths);

            // Respaldo: el conteo de arriba ya lo evita, pero si validar_limite_multimedia() rechaza el INSERT por el
            // límite de fotos (no el de video) se informa igual. Cualquier otro error queda registrado.
            if (FotoMascota::esErrorDeLimiteDeFotos($exception)) {
                return to_route('admin.mascotas.show', $mascota)->withErrors([$field => FotoMascota::MENSAJE_LIMITE]);
            }

            report($exception);

            return to_route('admin.mascotas.show', $mascota)->withErrors([$field => 'No se pudo guardar la foto. Inténtalo de nuevo.']);
        } catch (\Throwable $exception) {
            $this->deletePhotos($imagenSegura, $paths);

            throw $exception;
        }

        if ($freeSlots !== null) {
            $this->deletePhotos($imagenSegura, $paths);

            return to_route('admin.mascotas.show', $mascota)->withErrors([$field => FotoMascota::mensajeEspaciosLibres($freeSlots)]);
        }

        return to_route('admin.mascotas.show', $mascota)
            ->with('status', count($paths) === 1 ? 'La foto fue agregada correctamente.' : 'Se agregaron '.count($paths).' fotos correctamente.');
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

    /** Borra las imágenes (y sus miniaturas) guardadas en esta operación; nunca las anteriores. */
    private function deletePhotos(ImagenSegura $imagenSegura, array $paths): void
    {
        foreach ($paths as $path) {
            $imagenSegura->eliminar($path);
        }
    }

    /** En una selección de varias fotos indica cuál falló: "Foto n.º 2: ", "Foto adicional n.º 1: ". */
    private function photoErrorPrefix(string $key): string
    {
        if (! preg_match('/^(fotos|fotos_adicionales)\.(\d+)$/', $key, $match)) {
            return '';
        }

        return ($match[1] === 'fotos' ? 'Foto' : 'Foto adicional').' n.º '.($match[2] + 1).': ';
    }

    private function findPetPhoto(Mascota $mascota, FotoMascota $foto): FotoMascota
    {
        return $mascota->fotos()
            ->whereKey($foto->id)
            ->where('tipo', 'foto')
            ->firstOrFail();
    }
}
