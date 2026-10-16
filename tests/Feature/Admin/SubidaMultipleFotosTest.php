<?php

namespace Tests\Feature\Admin;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\User;
use App\Services\ImagenSegura;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImagenesDePrueba as Img;
use Tests\TestCase;

/**
 * Subida de varias fotos (registro y ficha), límite de 10 MB y 25 MP, mensajes en español y página 413.
 * Valida el servidor y el HTML; la optimización en el navegador (Alpine + canvas) se prueba manualmente.
 * Usa pol_bolivia_test (marca POL-BOLIVIA-PRUEBAS, tests/TestCase.php) y el disco falso.
 */
class SubidaMultipleFotosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create(['rol' => 'administrador']);
    }

    public function test_acepta_una_foto_de_9_mb_y_rechaza_una_de_10_1_mb_con_un_mensaje_en_espanol(): void
    {
        $mascota = $this->mascota(fotos: 1);

        $this->subir($mascota, [UploadedFile::fake()->image('nueve.jpg', 800, 600)->size(9 * 1024)])->assertSessionHasNoErrors();
        $this->assertSame(2, $mascota->fotos()->count());

        $this->subir($mascota, [UploadedFile::fake()->image('pesada.jpg', 800, 600)->size(10342)])
            ->assertSessionHasErrors(['fotos.0' => 'La imagen foto n.º 1 no debe pesar más de 10 MB.']);
        $this->assertSame(2, $mascota->fotos()->count());
    }

    public function test_rechaza_archivos_que_no_son_imagenes_formatos_no_admitidos_e_imagenes_pequenas_en_espanol(): void
    {
        $mascota = $this->mascota(fotos: 1);
        // PDF con nombre .jpg: UploadedFile real (no el falso, que deduce el tipo del nombre) para que el tipo
        // se lea del contenido, como en una subida desde el navegador.
        $pdf = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($pdf, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF");
        $this->beforeApplicationDestroyed(fn () => @unlink($pdf));

        $casos = [
            'texto' => [UploadedFile::fake()->createWithContent('notas.txt', 'no es una imagen'), 'El archivo foto n.º 1 debe ser una imagen.'],
            'pdf renombrado' => [new UploadedFile($pdf, 'foto.jpg', 'image/jpeg', null, true), 'El archivo foto n.º 1 debe ser una imagen.'],
            'jpg dañado' => [UploadedFile::fake()->createWithContent('danada.jpg', "\xFF\xD8\xFF\xE0".str_repeat("\x00", 200)), 'Foto n.º 1: El archivo no es una imagen válida o está dañado.'],
            'gif' => [UploadedFile::fake()->image('animada.gif', 500, 500), 'La imagen foto n.º 1 debe ser JPG, PNG o WEBP.'],
            '300 × 300' => [UploadedFile::fake()->createWithContent('chica.jpg', Img::jpeg(300, 300)), 'Foto n.º 1: La imagen es demasiado pequeña (300 × 300 px). El mínimo es 400 × 400 px.'],
        ];

        foreach ($casos as $caso => [$archivo, $mensaje]) {
            $this->subir($mascota, [$archivo])->assertSessionHasErrors(['fotos.0' => $mensaje]);
        }

        $this->assertSame(1, $mascota->fotos()->count());
        $this->assertSame([], $this->archivosNuevos());
    }

    public function test_rechaza_una_imagen_de_mas_de_25_megapixeles_leyendo_solo_su_cabecera(): void
    {
        $mascota = $this->mascota(fotos: 1);

        // Solo la cabecera: GD no podría decodificarla, así que el mensaje prueba que se rechazó antes de abrirla.
        $this->subir($mascota, [UploadedFile::fake()->createWithContent('enorme.png', Img::cabeceraPng(6000, 4500))])
            ->assertSessionHasErrors(['fotos.0' => 'Foto n.º 1: La imagen tiene una resolución demasiado alta (máximo 25 megapíxeles).']);

        $this->assertSame([], $this->archivosNuevos());
    }

    public function test_una_foto_de_4000_x_3000_se_reduce_a_1600_px_y_tiene_su_miniatura(): void
    {
        $mascota = $this->mascota(fotos: 1);

        $this->subir($mascota, [UploadedFile::fake()->createWithContent('celular.jpg', Img::jpeg(4000, 3000))])->assertSessionHasNoErrors();

        $foto = $mascota->fotos()->where('orden', 2)->sole();
        $this->assertSame([1600, 1200], Img::dimensiones($foto->url));
        $this->assertSame([400, 300], Img::dimensiones(ImagenSegura::rutaMiniatura($foto->url)));
    }

    public function test_un_png_transparente_subido_junto_con_otras_fotos_sigue_siendo_png_transparente(): void
    {
        $mascota = $this->mascota(fotos: 1);

        $this->subir($mascota, [
            UploadedFile::fake()->createWithContent('foto.jpg', Img::jpeg(800, 600)),
            UploadedFile::fake()->createWithContent('logo.png', Img::pngTransparente(800, 800)),
        ])->assertSessionHasNoErrors();

        $png = $mascota->fotos()->where('orden', 3)->sole();
        $this->assertStringEndsWith('.png', $png->url);
        $imagen = imagecreatefromstring(Storage::disk('public')->get($png->url));
        $this->assertSame(127, (imagecolorat($imagen, 0, 0) >> 24) & 0x7F, 'la esquina sigue transparente');
    }

    public function test_registro_con_la_principal_y_tres_adicionales_crea_cuatro_fotos_con_una_sola_principal(): void
    {
        $this->registrar([
            'foto_principal' => $this->jpeg('principal.jpg'),
            'fotos_adicionales' => [$this->jpeg('a.jpg'), $this->jpeg('b.jpg'), $this->jpeg('c.jpg')],
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.mascotas.index'));

        $fotos = Mascota::query()->where('nombre', 'Canela')->sole()->fotos()->orderBy('orden')->get();
        $this->assertSame([1, 2, 3, 4], $fotos->pluck('orden')->all());
        $this->assertSame([true, false, false, false], $fotos->pluck('es_principal')->all());
        $fotos->each(fn (FotoMascota $foto) => Storage::disk('public')->assertExists([$foto->url, ImagenSegura::rutaMiniatura($foto->url)]));
    }

    public function test_registro_rechaza_mas_de_tres_adicionales_sin_registrar_nada(): void
    {
        $this->registrar([
            'foto_principal' => $this->jpeg('principal.jpg'),
            'fotos_adicionales' => [$this->jpeg('a.jpg'), $this->jpeg('b.jpg'), $this->jpeg('c.jpg'), $this->jpeg('d.jpg')],
        ])->assertSessionHasErrors(['fotos_adicionales' => 'Puedes subir como máximo 3 fotos adicionales (4 en total con la principal).']);

        $this->assertSame(0, Mascota::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_desde_la_ficha_se_agregan_varias_fotos_sin_cambiar_la_principal(): void
    {
        $mascota = $this->mascota(fotos: 1);
        $principal = $mascota->fotos()->sole();

        $this->subir($mascota, [$this->jpeg('a.jpg'), $this->jpeg('b.jpg'), $this->jpeg('c.jpg')])
            ->assertSessionHasNoErrors()->assertSessionHas('status', 'Se agregaron 3 fotos correctamente.');

        $fotos = $mascota->fotos()->orderBy('orden')->get();
        $this->assertSame([1, 2, 3, 4], $fotos->pluck('orden')->all());
        $this->assertSame([$principal->id], $fotos->where('es_principal', true)->pluck('id')->values()->all());
    }

    public function test_una_seleccion_que_no_cabe_se_rechaza_entera(): void
    {
        $mascota = $this->mascota(fotos: 3);

        $this->subir($mascota, [$this->jpeg('a.jpg'), $this->jpeg('b.jpg')])
            ->assertSessionHasErrors(['fotos' => 'Solo puedes agregar 1 foto más a esta mascota.']);

        $this->assertSame(3, $mascota->fotos()->count());
        $this->assertSame([], $this->archivosNuevos());
    }

    public function test_con_cuatro_fotos_no_hay_formulario_y_la_ruta_rechaza_la_subida(): void
    {
        $mascota = $this->mascota(fotos: 4);

        $this->actingAs($this->admin)->get(route('admin.mascotas.show', $mascota))->assertOk()
            ->assertSee('Esta mascota ya tiene el máximo de 4 fotos. Elimina una para subir otra.')
            ->assertDontSee('name="fotos[]"', false);

        $this->subir($mascota, [$this->jpeg('a.jpg')])->assertSessionHasErrors(['fotos' => FotoMascota::MENSAJE_LIMITE]);
        $this->assertSame(4, $mascota->fotos()->count());
        $this->assertSame([], $this->archivosNuevos());
    }

    public function test_un_archivo_invalido_en_la_seleccion_rechaza_todas_sin_dejar_archivos(): void
    {
        $mascota = $this->mascota(fotos: 1);

        $this->subir($mascota, [$this->jpeg('buena.jpg'), UploadedFile::fake()->createWithContent('chica.jpg', Img::jpeg(300, 300))])
            ->assertSessionHasErrors(['fotos.1' => 'Foto n.º 2: La imagen es demasiado pequeña (300 × 300 px). El mínimo es 400 × 400 px.']);

        $this->assertSame(1, $mascota->fotos()->count());
        $this->assertSame([], $this->archivosNuevos());
    }

    public function test_se_informa_el_error_de_cada_foto_invalida_de_la_seleccion(): void
    {
        $mascota = $this->mascota(fotos: 1);

        $this->subir($mascota, [
            UploadedFile::fake()->createWithContent('chica.jpg', Img::jpeg(300, 300)),
            $this->jpeg('buena.jpg'),
            UploadedFile::fake()->image('pesada.jpg', 800, 600)->size(10342),
        ])->assertSessionHasErrors([
            'fotos.0' => 'Foto n.º 1: La imagen es demasiado pequeña (300 × 300 px). El mínimo es 400 × 400 px.',
            'fotos.2' => 'La imagen foto n.º 3 no debe pesar más de 10 MB.',
        ])->assertSessionDoesntHaveErrors('fotos.1');

        $this->assertSame(1, $mascota->fotos()->count());
        $this->assertSame([], $this->archivosNuevos());
    }

    public function test_si_otra_subida_ocupa_los_espacios_mientras_se_procesan_las_fotos_se_rechaza_antes_del_trigger(): void
    {
        $mascota = $this->mascota(fotos: 2);
        $principal = $mascota->fotos()->where('es_principal', true)->sole();

        // Al guardar la primera foto (antes de la transacción), "otra subida" agrega una foto a la mascota.
        $this->app->instance(ImagenSegura::class, new class($mascota) extends ImagenSegura
        {
            private bool $hecho = false;

            public function __construct(private Mascota $mascota) {}

            public function guardar(UploadedFile $archivo, string $carpeta, string $disco = 'public'): string
            {
                if (! $this->hecho) {
                    $this->hecho = true;
                    FotoMascota::create(['mascota_id' => $this->mascota->id, 'tipo' => 'foto', 'url' => 'mascotas/otra-subida.jpg', 'es_principal' => false, 'orden' => 10]);
                }

                return parent::guardar($archivo, $carpeta, $disco);
            }
        });

        // El conteo con la mascota bloqueada lo detecta: mensaje de espacios libres (el trigger daría el del límite).
        $this->subir($mascota, [$this->jpeg('a.jpg'), $this->jpeg('b.jpg')])
            ->assertSessionHasErrors(['fotos' => 'Solo puedes agregar 1 foto más a esta mascota.']);

        $this->assertSame(3, $mascota->fotos()->count());
        $this->assertTrue($principal->fresh()->es_principal);
        $this->assertSame([], $this->archivosNuevos());
    }

    public function test_el_respaldo_del_trigger_distingue_el_limite_de_fotos_del_de_video(): void
    {
        $error = fn (string $codigo, string $mensaje) => new QueryException('pgsql', 'insert into fotos_mascota ...', [],
            new class($codigo, $mensaje) extends \PDOException
            {
                public function __construct(string $codigo, string $mensaje)
                {
                    parent::__construct($mensaje);
                    $this->code = $codigo;
                }
            });

        $this->assertTrue(FotoMascota::esErrorDeLimiteDeFotos($error('P0001', 'SQLSTATE[P0001]: Raise exception: 7 ERROR: Una mascota no puede tener más de 4 fotos')));
        $this->assertFalse(FotoMascota::esErrorDeLimiteDeFotos($error('P0001', 'SQLSTATE[P0001]: Raise exception: 7 ERROR: Una mascota no puede tener más de 1 video')));
        $this->assertFalse(FotoMascota::esErrorDeLimiteDeFotos($error('23505', 'SQLSTATE[23505]: Unique violation: idx_orden_unico_por_mascota')));
    }

    public function test_si_falla_el_guardado_de_la_segunda_foto_no_queda_nada_de_la_subida(): void
    {
        $mascota = $this->mascota(fotos: 2);
        $anteriores = $this->huellas();
        // Cada foto son dos escrituras (imagen y miniatura): la 3.ª es la imagen de la segunda foto.
        Img::discoQueFallaAlEscribir(fallarEn: 3);

        $this->subir($mascota, [$this->jpeg('a.jpg'), $this->jpeg('b.jpg')])
            ->assertSessionHasErrors(['fotos' => 'Foto n.º 2: No se pudo guardar la imagen. Inténtalo de nuevo.']);

        $this->assertSame(2, $mascota->fotos()->count());
        $this->assertSame($anteriores, $this->huellas(), 'las fotos anteriores siguen intactas y no quedan archivos nuevos');
    }

    public function test_si_el_trigger_de_limite_rechaza_la_subida_se_informa_en_espanol_sin_archivos_huerfanos(): void
    {
        $mascota = $this->mascota(fotos: 2);
        $anteriores = $this->huellas();
        $simulada = false;

        // Otra subida completa el límite justo antes del primer INSERT: validar_limite_multimedia() lo rechaza.
        FotoMascota::creating(function () use ($mascota, &$simulada): void {
            if (! $simulada) {
                $simulada = true;
                DB::table('fotos_mascota')->insert([
                    ['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => 'mascotas/ajena-1.jpg', 'es_principal' => false, 'orden' => 50],
                    ['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => 'mascotas/ajena-2.jpg', 'es_principal' => false, 'orden' => 51],
                ]);
            }
        });

        $this->subir($mascota, [$this->jpeg('a.jpg'), $this->jpeg('b.jpg')])
            ->assertRedirect(route('admin.mascotas.show', $mascota))
            ->assertSessionHasErrors(['fotos' => FotoMascota::MENSAJE_LIMITE]);

        $this->assertTrue($simulada);
        $this->assertSame(2, $mascota->fotos()->count(), 'la transacción se deshizo');
        $this->assertSame($anteriores, $this->huellas());
    }

    public function test_el_campo_antiguo_foto_sigue_funcionando_y_conserva_la_principal(): void
    {
        $mascota = $this->mascota(fotos: 1);
        $principal = $mascota->fotos()->sole();

        $this->actingAs($this->admin)->post(route('admin.mascotas.fotos.store', $mascota), ['foto' => $this->jpeg('una.jpg')])
            ->assertSessionHasNoErrors()->assertSessionHas('status', 'La foto fue agregada correctamente.');

        $this->assertSame(2, $mascota->fotos()->count());
        $this->assertTrue($principal->fresh()->es_principal);
    }

    public function test_una_peticion_mayor_que_post_max_size_muestra_la_pagina_413_en_espanol(): void
    {
        $mascota = $this->mascota(fotos: 1);
        $ficha = route('admin.mascotas.show', $mascota);

        $this->actingAs($this->admin)
            ->call('POST', route('admin.mascotas.fotos.store', $mascota), [], [], [], ['CONTENT_LENGTH' => (string) (200 * 1024 * 1024), 'HTTP_REFERER' => $ficha])
            ->assertStatus(413)
            ->assertSee('Las fotos seleccionadas superan el tamaño total permitido. Sube menos fotos a la vez o imágenes más livianas (máximo 10 MB cada una).')
            ->assertSee('<a href="'.$ficha.'" class="boton">Volver</a>', false);
    }

    public function test_los_formularios_incluyen_el_optimizador_y_los_selectores_correctos(): void
    {
        $mascota = $this->mascota(fotos: 1);
        $ayuda = 'JPG, PNG o WEBP · máximo 10 MB por foto · mínimo 400 × 400 px · hasta 4 fotos por mascota. Las fotos grandes se optimizan automáticamente antes de subir.';

        $this->actingAs($this->admin)->get(route('admin.mascotas.show', $mascota))->assertOk()
            ->assertSee('x-data="optimizadorFotos(', false)
            ->assertSee('x-on:pageshow.window="enviando = false"', false)
            ->assertSee('name="fotos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required data-maximo="3"', false)
            ->assertSee('Fotos: 1 de 4 · Puedes agregar 3 más')
            ->assertSee($ayuda);

        $this->actingAs($this->admin)->get(route('admin.mascotas.create'))->assertOk()
            ->assertSee('x-data="optimizadorFotos(', false)
            ->assertSee('x-on:pageshow.window="enviando = false"', false)
            ->assertSee('name="foto_principal" type="file" accept="image/jpeg,image/png,image/webp" required data-maximo="1"', false)
            ->assertSee('name="fotos_adicionales[]" type="file" accept="image/jpeg,image/png,image/webp" multiple', false)
            ->assertSee($ayuda);
    }

    public function test_los_errores_de_los_campos_de_mascota_se_muestran_en_espanol(): void
    {
        $this->registrar([
            'nombre' => 'Luna2', 'especie' => 'loro', 'edad' => 'dos', 'tamano' => '', 'foto_principal' => $this->jpeg('principal.jpg'),
        ])->assertSessionHasErrors([
            'nombre' => 'El formato de nombre no es válido.',
            'especie' => 'El valor seleccionado en especie no es válido.',
            'edad' => 'El campo edad debe ser un número entero.',
            'tamano' => 'El campo tamaño es obligatorio.',
        ]);

        $this->registrar(['edad' => 15, 'edad_en' => 'meses', 'foto_principal' => $this->jpeg('principal.jpg')])
            ->assertSessionHasErrors(['edad' => 'El campo edad no debe ser mayor que 11.']);
        $this->registrar(['foto_principal' => UploadedFile::fake()->image('pesada.jpg', 800, 600)->size(10342)])
            ->assertSessionHasErrors(['foto_principal' => 'La imagen foto principal no debe pesar más de 10 MB.']);
    }

    /** Mascota disponible con $fotos fotos "antiguas" (la primera es la principal). */
    private function mascota(int $fotos): Mascota
    {
        return DB::transaction(function () use ($fotos): Mascota {
            $mascota = Mascota::create(['nombre' => 'Luna', 'especie' => 'perro', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $this->admin->id]);

            foreach (range(1, $fotos) as $orden) {
                $ruta = "mascotas/{$mascota->id}/antigua-{$orden}.jpg";
                Storage::disk('public')->put($ruta, Img::jpeg(500, 500));
                FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => $ruta, 'es_principal' => $orden === 1, 'orden' => $orden]);
            }

            return $mascota;
        });
    }

    private function subir(Mascota $mascota, array $archivos)
    {
        return $this->actingAs($this->admin)->from(route('admin.mascotas.show', $mascota))
            ->post(route('admin.mascotas.fotos.store', $mascota), ['fotos' => $archivos]);
    }

    private function registrar(array $datos)
    {
        return $this->actingAs($this->admin)->from(route('admin.mascotas.create'))->post(route('admin.mascotas.store'), [
            'nombre' => 'Canela', 'especie' => 'gato', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'pequeno',
            'descripcion' => 'Prueba de registro.', ...$datos,
        ]);
    }

    private function jpeg(string $nombre): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, Img::jpeg(800, 600));
    }

    /** Archivos del disco que no son las fotos "antiguas" creadas por mascota(). */
    private function archivosNuevos(): array
    {
        return array_values(array_filter(Storage::disk('public')->allFiles(), fn (string $f) => ! str_contains($f, '/antigua-')));
    }

    /** Huella de cada archivo del disco: detecta archivos nuevos, borrados o modificados. */
    private function huellas(): array
    {
        return collect(Storage::disk('public')->allFiles())->mapWithKeys(fn (string $f) => [$f => md5(Storage::disk('public')->get($f))])->sort()->all();
    }
}
