<?php

namespace Tests\Feature\Admin;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\User;
use App\Services\ImagenSegura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Fotos de mascotas: límite de 4, imágenes dañadas y eliminación segura sin errores 500.
 */
class FotosMascotaTest extends TestCase
{
    use RefreshDatabase;

    private const LIMITE = 'La mascota ya tiene 4 fotos, el máximo permitido. Elimina una antes de agregar otra.';

    private const UNICA = 'No se puede eliminar la única foto de una mascota disponible. Agrega otra foto antes de eliminar esta.';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create(['rol' => 'administrador']);
    }

    public function test_se_sube_la_cuarta_foto_y_la_pantalla_indica_que_se_alcanzo_el_limite(): void
    {
        $mascota = $this->mascota(fotos: 3);

        $this->subir($mascota)->assertSessionHasNoErrors()->assertRedirect(route('admin.mascotas.show', $mascota));
        $this->assertSame(4, $mascota->fotos()->count());

        $this->actingAs($this->admin)->get(route('admin.mascotas.show', $mascota))
            ->assertOk()
            ->assertSee('4 de 4 fotos guardadas')
            ->assertSee('La mascota ya tiene 4 fotos, el máximo permitido. Elimina una para agregar otra.')
            ->assertDontSee('Subir foto');
    }

    public function test_no_se_sube_una_quinta_foto(): void
    {
        $mascota = $this->mascota(fotos: 4);

        $this->subir($mascota)->assertSessionHasErrors(['foto' => self::LIMITE]);

        $this->assertSame(4, $mascota->fotos()->count());
        $this->assertSame(4, count(Storage::disk('public')->allFiles()), 'no se guarda ningún archivo nuevo');
    }

    public function test_el_limite_se_respeta_aunque_otra_subida_lo_complete_al_mismo_tiempo(): void
    {
        $mascota = $this->mascota(fotos: 3);
        $rutaSubida = null;

        // Otra petición agrega la cuarta foto mientras esta procesa su imagen.
        $this->app->instance(ImagenSegura::class, new class($mascota, $rutaSubida) extends ImagenSegura
        {
            public function __construct(private Mascota $mascota, private ?string &$ruta) {}

            public function guardar(UploadedFile $archivo, string $carpeta, string $disco = 'public'): string
            {
                FotoMascota::create(['mascota_id' => $this->mascota->id, 'tipo' => 'foto', 'url' => 'mascotas/otra-peticion.jpg', 'es_principal' => false, 'orden' => 4]);

                return $this->ruta = parent::guardar($archivo, $carpeta, $disco);
            }
        });

        $this->subir($mascota)->assertSessionHasErrors(['foto' => self::LIMITE]);

        $this->assertSame(4, $mascota->fotos()->count());
        $this->assertNotNull($rutaSubida);
        Storage::disk('public')->assertMissing($rutaSubida);
    }

    public function test_una_imagen_danada_se_rechaza_al_subirla_y_al_registrar_la_mascota(): void
    {
        $mascota = $this->mascota(fotos: 1);

        $this->subir($mascota, $this->imagenDanada())->assertSessionHasErrors(['foto' => 'El archivo no es una imagen válida o está dañado.']);
        $this->assertSame(1, $mascota->fotos()->count());

        $mascotas = Mascota::count();
        $this->actingAs($this->admin)->post(route('admin.mascotas.store'), [
            'nombre' => 'Nube', 'especie' => 'gato', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'pequeno',
            'foto_principal' => $this->imagenDanada(),
        ])->assertSessionHasErrors(['foto_principal' => 'El archivo no es una imagen válida o está dañado.']);

        $this->assertSame($mascotas, Mascota::count());
        $this->assertSame(1, count(Storage::disk('public')->allFiles()));
    }

    public function test_no_se_elimina_la_unica_foto_de_una_mascota_disponible(): void
    {
        $mascota = $this->mascota(fotos: 1);
        $foto = $mascota->fotos()->sole();

        $this->actingAs($this->admin)->get(route('admin.mascotas.show', $mascota))
            ->assertSee('Es la única foto: agrega otra antes de eliminarla.')
            ->assertDontSee(route('admin.mascotas.fotos.destroy', [$mascota, $foto]), false);

        // Aunque se envíe la petición a mano.
        $this->eliminar($mascota, $foto)->assertSessionHasErrors(['fotos' => self::UNICA]);

        $this->assertModelExists($foto);
        Storage::disk('public')->assertExists($foto->url);
    }

    public function test_al_eliminar_la_principal_pasa_a_serlo_la_siguiente_por_orden(): void
    {
        $mascota = $this->mascota(fotos: 3);
        [$principal, $segunda, $tercera] = $mascota->fotos()->orderBy('orden')->get()->all();

        $this->eliminar($mascota, $principal)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'La foto fue eliminada. La foto en la posición 2 pasó a ser la principal.');

        $this->assertModelMissing($principal);
        Storage::disk('public')->assertMissing($principal->url);
        $this->assertTrue($segunda->fresh()->es_principal);
        $this->assertFalse($tercera->fresh()->es_principal);
        $this->assertSame(1, $mascota->fotos()->where('es_principal', true)->count());
    }

    public function test_al_eliminar_una_foto_secundaria_la_principal_no_cambia(): void
    {
        $mascota = $this->mascota(fotos: 2);
        [$principal, $segunda] = $mascota->fotos()->orderBy('orden')->get()->all();

        $this->eliminar($mascota, $segunda)->assertSessionHas('status', 'La foto fue eliminada correctamente.');

        $this->assertModelMissing($segunda);
        Storage::disk('public')->assertMissing($segunda->url);
        $this->assertTrue($principal->fresh()->es_principal);
    }

    public function test_tras_eliminar_una_foto_se_puede_volver_a_subir_hasta_el_limite(): void
    {
        $mascota = $this->mascota(fotos: 4);
        $ultima = $mascota->fotos()->orderByDesc('orden')->first();

        $this->eliminar($mascota, $ultima)->assertSessionHasNoErrors();
        $this->subir($mascota)->assertSessionHasNoErrors();

        $this->assertSame(4, $mascota->fotos()->count());
        $this->assertSame([1, 2, 3, 4], $mascota->fotos()->orderBy('orden')->pluck('orden')->all());
    }

    /** Mascota disponible con N fotos reales en el disco de prueba; la primera es la principal. */
    private function mascota(int $fotos): Mascota
    {
        return DB::transaction(function () use ($fotos): Mascota {
            $mascota = Mascota::create(['nombre' => 'Luna', 'especie' => 'perro', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $this->admin->id]);

            foreach (range(1, $fotos) as $orden) {
                $ruta = "mascotas/{$mascota->id}/foto-{$orden}.jpg";
                Storage::disk('public')->put($ruta, 'contenido');
                FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => $ruta, 'es_principal' => $orden === 1, 'orden' => $orden]);
            }

            return $mascota;
        });
    }

    private function subir(Mascota $mascota, ?UploadedFile $archivo = null)
    {
        return $this->actingAs($this->admin)
            ->from(route('admin.mascotas.show', $mascota))
            ->post(route('admin.mascotas.fotos.store', $mascota), ['foto' => $archivo ?? UploadedFile::fake()->image('foto.jpg', 600, 600)]);
    }

    private function eliminar(Mascota $mascota, FotoMascota $foto)
    {
        return $this->actingAs($this->admin)
            ->from(route('admin.mascotas.show', $mascota))
            ->delete(route('admin.mascotas.fotos.destroy', [$mascota, $foto]));
    }

    /** Cabecera JPEG válida y contenido dañado: supera la regla "image" pero GD no puede leerlo. */
    private function imagenDanada(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('danada.jpg', "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00".str_repeat("\x00", 200));
    }
}
