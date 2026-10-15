<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\User;
use App\Services\ImagenSegura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImagenesDePrueba as Img;
use Tests\TestCase;

/**
 * pol:generar-miniaturas sobre fotos ya guardadas: solo crea miniaturas, es idempotente, informa los
 * fallos y no toca los originales ni la base de datos. Usa el disco falso y pol_bolivia_test.
 */
class GenerarMiniaturasCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, FotoMascota> */
    private array $fotos = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $admin = User::factory()->create(['rol' => 'administrador']);

        // Dos mascotas (máximo 4 fotos cada una) con todos los casos.
        $this->mascota($admin, [
            'sin_miniatura' => ['grande.jpg', Img::jpeg(2000, 1000)],
            'con_miniatura' => ['logo.png', Img::pngTransparente(600, 600)],
            'miniatura_danada' => ['otra.jpg', Img::jpeg(900, 900)],
            'sin_archivo' => ['perdida.jpg', null],
        ]);
        $this->mascota($admin, [
            'celular' => ['celular.jpg', Img::jpegConOrientacion(800, 400, 6)],
            'enorme' => ['enorme.png', Img::cabeceraPng(12000, 12000)],
        ]);

        Storage::disk('public')->put(ImagenSegura::rutaMiniatura($this->fotos['con_miniatura']->url), Img::pngTransparente(400, 400));
        Storage::disk('public')->put(ImagenSegura::rutaMiniatura($this->fotos['miniatura_danada']->url), 'no es una imagen');
    }

    public function test_genera_las_miniaturas_que_faltan_sin_tocar_originales_ni_la_base_de_datos(): void
    {
        $originales = $this->huellasDeOriginales();
        $miniaturaExistente = $this->huella(ImagenSegura::rutaMiniatura($this->fotos['con_miniatura']->url));
        $registros = FotoMascota::query()->orderBy('id')->get()->toArray();
        $auditorias = Auditoria::count();

        [$codigo, $salida] = $this->ejecutar();

        $this->assertSame(1, $codigo, 'termina con error porque hay fallos');
        $this->assertMatchesRegularExpression('/Generadas\s*\|\s*3/', $salida);
        $this->assertMatchesRegularExpression('/Omitidas \(ya tenían miniatura válida\)\s*\|\s*1/', $salida);
        $this->assertMatchesRegularExpression('/Fallidas\s*\|\s*2/', $salida);
        $this->assertStringContainsString('No existe el archivo de la imagen.', $salida);
        $this->assertStringContainsString('La imagen es demasiado grande (12000 × 12000 px)', $salida);

        $this->assertSame([400, 200], Img::dimensiones(ImagenSegura::rutaMiniatura($this->fotos['sin_miniatura']->url)));
        $this->assertSame([400, 400], Img::dimensiones(ImagenSegura::rutaMiniatura($this->fotos['miniatura_danada']->url)), 'la miniatura dañada se regenera');
        $this->assertSame([200, 400], Img::dimensiones(ImagenSegura::rutaMiniatura($this->fotos['celular']->url)), 'respeta la orientación EXIF');
        $this->assertSame($miniaturaExistente, $this->huella(ImagenSegura::rutaMiniatura($this->fotos['con_miniatura']->url)), 'no sobrescribe una miniatura válida');
        Storage::disk('public')->assertMissing(ImagenSegura::rutaMiniatura($this->fotos['enorme']->url));

        $this->assertSame($originales, $this->huellasDeOriginales(), 'los originales no cambian');
        $this->assertSame($registros, FotoMascota::query()->orderBy('id')->get()->toArray(), 'la base de datos no cambia');
        $this->assertSame($auditorias, Auditoria::count());
    }

    public function test_es_idempotente(): void
    {
        $this->ejecutar();
        $miniaturas = $this->huellasDeMiniaturas();

        [$codigo, $salida] = $this->ejecutar();

        $this->assertSame(1, $codigo);
        $this->assertMatchesRegularExpression('/Generadas\s*\|\s*0/', $salida);
        $this->assertMatchesRegularExpression('/Omitidas \(ya tenían miniatura válida\)\s*\|\s*4/', $salida);
        $this->assertMatchesRegularExpression('/Fallidas\s*\|\s*2/', $salida);
        $this->assertSame($miniaturas, $this->huellasDeMiniaturas(), 'la segunda ejecución no reescribe nada');
    }

    public function test_simular_no_escribe_ningun_archivo(): void
    {
        $archivos = Storage::disk('public')->allFiles();

        [$codigo, $salida] = $this->ejecutar(['--simular' => true]);

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('SIMULACIÓN: no se escribió ningún archivo.', $salida);
        $this->assertMatchesRegularExpression('/Por generar\s*\|\s*3/', $salida);
        $this->assertSame($archivos, Storage::disk('public')->allFiles());
    }

    public function test_sin_fallos_termina_correctamente(): void
    {
        foreach (['sin_archivo', 'enorme'] as $clave) {
            DB::table('fotos_mascota')->where('id', $this->fotos[$clave]->id)->delete();
        }

        [$codigo, $salida] = $this->ejecutar();

        $this->assertSame(0, $codigo);
        $this->assertMatchesRegularExpression('/Fallidas\s*\|\s*0/', $salida);
    }

    public function test_un_fallo_al_escribir_una_miniatura_se_informa_y_no_detiene_las_demas(): void
    {
        Img::discoQueFallaAlEscribir(fallarEn: 1);

        [$codigo, $salida] = $this->ejecutar();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('No se pudo guardar la miniatura.', $salida);
        $this->assertMatchesRegularExpression('/Generadas\s*\|\s*2/', $salida);
        $this->assertMatchesRegularExpression('/Fallidas\s*\|\s*3/', $salida);
    }

    public function test_una_ruta_que_sale_del_disco_se_informa_como_fallida_y_no_detiene_las_demas(): void
    {
        FotoMascota::create(['mascota_id' => $this->fotos['celular']->mascota_id, 'tipo' => 'foto', 'url' => '../fuera-del-disco.jpg', 'es_principal' => false, 'orden' => 3]);

        [$codigo, $salida] = $this->ejecutar();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('La ruta de la imagen no es válida.', $salida);
        $this->assertStringNotContainsString('fuera-del-disco', $salida, 'no muestra la ruta');
        $this->assertMatchesRegularExpression('/Generadas\s*\|\s*3/', $salida);
        $this->assertMatchesRegularExpression('/Fallidas\s*\|\s*3/', $salida);
        $this->assertSame([400, 200], Img::dimensiones(ImagenSegura::rutaMiniatura($this->fotos['sin_miniatura']->url)));
    }

    /** @param array<string, array{0: string, 1: ?string}> $fotos */
    private function mascota(User $admin, array $fotos): void
    {
        DB::transaction(function () use ($admin, $fotos): void {
            $mascota = Mascota::create(['nombre' => 'Luna', 'especie' => 'perro', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $admin->id]);
            $orden = 0;

            foreach ($fotos as $clave => [$nombre, $contenido]) {
                $ruta = "mascotas/{$mascota->id}/{$nombre}";
                $contenido === null || Storage::disk('public')->put($ruta, $contenido);
                $this->fotos[$clave] = FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => $ruta, 'es_principal' => $orden === 0, 'orden' => ++$orden]);
            }
        });
    }

    private function ejecutar(array $opciones = []): array
    {
        $codigo = Artisan::call('pol:generar-miniaturas', $opciones);

        return [$codigo, Artisan::output()];
    }

    private function huella(string $ruta): string
    {
        return hash('sha256', Storage::disk('public')->get($ruta));
    }

    private function huellasDeOriginales(): array
    {
        return collect($this->fotos)->filter(fn (FotoMascota $f) => Storage::disk('public')->exists($f->url))
            ->map(fn (FotoMascota $f) => $this->huella($f->url))->all();
    }

    private function huellasDeMiniaturas(): array
    {
        return collect(Storage::disk('public')->allFiles())->filter(fn (string $f) => str_contains($f, '/miniaturas/'))
            ->mapWithKeys(fn (string $f) => [$f => $this->huella($f)])->sort()->all();
    }
}
