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
use Tests\Support\ImagenesDePrueba as Img;
use Tests\TestCase;

/**
 * Procesamiento de las fotos subidas: tamaño máximo guardado, miniaturas, proporción, transparencia,
 * orientación, límites de dimensiones y fallos de escritura.
 */
class ImagenesMascotaTest extends TestCase
{
    use RefreshDatabase;

    private const NO_GUARDADA = 'No se pudo guardar la imagen. Inténtalo de nuevo.';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create(['rol' => 'administrador']);
    }

    public function test_una_foto_grande_se_reduce_a_1600_px_y_genera_una_miniatura_de_400_px_con_la_misma_proporcion(): void
    {
        $mascota = $this->mascota();

        $this->subir($mascota, $this->archivo('grande.jpg', Img::jpeg(2400, 1800)))->assertSessionHasNoErrors();

        $foto = $this->ultimaFoto($mascota);
        $this->assertStringEndsWith('.jpg', $foto->url);
        $this->assertSame([1600, 1200], Img::dimensiones($foto->url));
        $this->assertSame([400, 300], Img::dimensiones(ImagenSegura::rutaMiniatura($foto->url)));
    }

    public function test_una_foto_pequena_no_se_agranda(): void
    {
        $mascota = $this->mascota();

        $this->subir($mascota, $this->archivo('mediana.jpg', Img::jpeg(600, 500)))->assertSessionHasNoErrors();

        $foto = $this->ultimaFoto($mascota);
        $this->assertSame([600, 500], Img::dimensiones($foto->url));
        $this->assertSame([400, 333], Img::dimensiones(ImagenSegura::rutaMiniatura($foto->url)));
    }

    public function test_un_png_conserva_su_transparencia_en_la_foto_y_en_la_miniatura(): void
    {
        $mascota = $this->mascota();

        $this->subir($mascota, $this->archivo('logo.png', Img::pngTransparente(800, 800)))->assertSessionHasNoErrors();

        $foto = $this->ultimaFoto($mascota);
        $miniatura = ImagenSegura::rutaMiniatura($foto->url);
        $this->assertStringEndsWith('.png', $foto->url);
        $this->assertStringEndsWith('.png', $miniatura);
        $this->assertSame(127, $this->alfa($foto->url, 0, 0), 'la esquina sigue transparente');
        $this->assertSame([400, 400], Img::dimensiones($miniatura));
        $this->assertGreaterThan(0, $this->alfa($miniatura, 0, 0));
    }

    public function test_se_respeta_la_orientacion_exif_de_las_fotos_de_celular(): void
    {
        $mascota = $this->mascota();

        // 800 × 400 con orientación 6: la foto se ve vertical.
        $this->subir($mascota, $this->archivo('celular.jpg', Img::jpegConOrientacion(800, 400, 6)))->assertSessionHasNoErrors();

        $foto = $this->ultimaFoto($mascota);
        $this->assertSame([400, 800], Img::dimensiones($foto->url));
        $this->assertSame([200, 400], Img::dimensiones(ImagenSegura::rutaMiniatura($foto->url)));
    }

    public function test_se_rechaza_una_imagen_menor_de_400_px(): void
    {
        $mascota = $this->mascota();

        $this->subir($mascota, $this->archivo('chica.jpg', Img::jpeg(399, 600)))
            ->assertSessionHasErrors(['foto' => 'La imagen es demasiado pequeña (399 × 600 px). El mínimo es 400 × 400 px.']);

        $this->assertSame(1, $mascota->fotos()->count());
        $this->assertSame([], $this->archivosNuevos());
    }

    public function test_una_imagen_de_dimensiones_excesivas_se_rechaza_antes_de_decodificarla(): void
    {
        $mascota = $this->mascota();
        $mensaje = 'La imagen es demasiado grande (12000 × 12000 px). El máximo es 10.000 px por lado.';

        $this->subir($mascota, $this->archivo('enorme.png', Img::cabeceraPng(12000, 12000)))->assertSessionHasErrors(['foto' => $mensaje]);
        $this->assertSame(1, $mascota->fotos()->count());

        $mascotas = Mascota::count();
        $this->registrar($this->archivo('enorme.png', Img::cabeceraPng(12000, 12000)))->assertSessionHasErrors(['foto_principal' => $mensaje]);
        $this->assertSame($mascotas, Mascota::count());
        $this->assertSame([], $this->archivosNuevos());
    }

    public function test_si_no_se_puede_escribir_la_foto_no_se_crea_el_registro(): void
    {
        $mascota = $this->mascota();
        Img::discoQueFallaAlEscribir(fallarEn: 1);

        $this->subir($mascota, $this->archivo('foto.jpg', Img::jpeg(800, 600)))->assertSessionHasErrors(['foto' => self::NO_GUARDADA]);

        $this->assertSame(1, $mascota->fotos()->count());
        $this->assertSame([], $this->archivosNuevos());
    }

    public function test_si_no_se_puede_escribir_la_miniatura_se_borra_la_foto_y_no_se_crea_el_registro(): void
    {
        $mascota = $this->mascota();
        Img::discoQueFallaAlEscribir(fallarEn: 2);

        $this->subir($mascota, $this->archivo('foto.jpg', Img::jpeg(800, 600)))->assertSessionHasErrors(['foto' => self::NO_GUARDADA]);

        $this->assertSame(1, $mascota->fotos()->count());
        $this->assertSame([], $this->archivosNuevos());
    }

    public function test_si_no_se_puede_escribir_la_foto_principal_no_se_registra_la_mascota(): void
    {
        $mascotas = Mascota::count();
        Img::discoQueFallaAlEscribir(fallarEn: 1);

        $this->registrar($this->archivo('foto.jpg', Img::jpeg(800, 600)))->assertSessionHasErrors(['foto_principal' => self::NO_GUARDADA]);

        $this->assertSame($mascotas, Mascota::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_al_eliminar_una_foto_tambien_se_borra_su_miniatura(): void
    {
        $mascota = $this->mascota();
        $this->subir($mascota, $this->archivo('foto.jpg', Img::jpeg(800, 600)))->assertSessionHasNoErrors();
        $foto = $this->ultimaFoto($mascota);
        Storage::disk('public')->assertExists(ImagenSegura::rutaMiniatura($foto->url));

        $this->actingAs($this->admin)->delete(route('admin.mascotas.fotos.destroy', [$mascota, $foto]))->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing($foto->url);
        Storage::disk('public')->assertMissing(ImagenSegura::rutaMiniatura($foto->url));
    }

    public function test_los_listados_usan_la_miniatura_y_las_fotos_antiguas_la_imagen_completa(): void
    {
        $conMiniatura = $this->mascota('Nube');
        $this->subir($conMiniatura, $this->archivo('foto.jpg', Img::jpeg(800, 600)))->assertSessionHasNoErrors();
        $nueva = $this->ultimaFoto($conMiniatura);
        $conMiniatura->fotos()->whereKeyNot($nueva->id)->update(['es_principal' => false]);
        $nueva->forceFill(['es_principal' => true])->save();

        $antigua = $this->mascota('Luna')->fotos()->sole(); // sin miniatura: anterior a esta etapa

        $listado = $this->actingAs($this->admin)->get(route('admin.mascotas.index'))->assertOk();
        $listado->assertSee(Storage::disk('public')->url(ImagenSegura::rutaMiniatura($nueva->url)), false);
        $listado->assertSee(Storage::disk('public')->url($antigua->url), false);

        $this->get(route('mascotas.catalogo'))->assertOk()
            ->assertSee(Storage::disk('public')->url(ImagenSegura::rutaMiniatura($nueva->url)), false);
    }

    /** Mascota disponible con una foto principal "antigua" (sin miniatura). */
    private function mascota(string $nombre = 'Luna'): Mascota
    {
        return DB::transaction(function () use ($nombre): Mascota {
            $mascota = Mascota::create(['nombre' => $nombre, 'especie' => 'perro', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $this->admin->id]);
            $ruta = "mascotas/{$mascota->id}/antigua.jpg";
            Storage::disk('public')->put($ruta, Img::jpeg(500, 500));
            FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => $ruta, 'es_principal' => true, 'orden' => 1]);

            return $mascota;
        });
    }

    private function subir(Mascota $mascota, UploadedFile $archivo)
    {
        return $this->actingAs($this->admin)->from(route('admin.mascotas.show', $mascota))
            ->post(route('admin.mascotas.fotos.store', $mascota), ['foto' => $archivo]);
    }

    private function registrar(UploadedFile $archivo)
    {
        return $this->actingAs($this->admin)->from(route('admin.mascotas.create'))->post(route('admin.mascotas.store'), [
            'nombre' => 'Canela', 'especie' => 'gato', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'pequeno',
            'descripcion' => 'Prueba de registro.', 'foto_principal' => $archivo,
        ]);
    }

    private function archivo(string $nombre, string $contenido): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, $contenido);
    }

    private function ultimaFoto(Mascota $mascota): FotoMascota
    {
        return $mascota->fotos()->orderByDesc('orden')->firstOrFail();
    }

    /** Archivos del disco que no son las fotos "antiguas" creadas por mascota(). */
    private function archivosNuevos(): array
    {
        return array_values(array_filter(Storage::disk('public')->allFiles(), fn (string $f) => ! str_ends_with($f, '/antigua.jpg')));
    }

    private function alfa(string $ruta, int $x, int $y): int
    {
        $imagen = imagecreatefromstring(Storage::disk('public')->get($ruta));
        $alfa = (imagecolorat($imagen, $x, $y) >> 24) & 0x7F;
        imagedestroy($imagen);

        return $alfa;
    }
}
