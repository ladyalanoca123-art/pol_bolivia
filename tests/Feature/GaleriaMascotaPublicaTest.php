<?php

namespace Tests\Feature;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\User;
use App\Services\ImagenSegura;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImagenesDePrueba as Img;
use Tests\TestCase;

/**
 * Galería de la ficha pública: HTML inicial (sin JavaScript se ve la principal), miniaturas y controles.
 * La interacción con Alpine (clics, flechas, teclado) se verifica en la prueba manual.
 */
class GaleriaMascotaPublicaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create(['rol' => 'administrador']);
    }

    public function test_con_varias_fotos_muestra_la_principal_todas_las_miniaturas_y_los_controles(): void
    {
        // La principal es la de orden 2: debe mostrarse primero.
        [$mascota, $fotos] = $this->mascota(fotos: 3, principal: 2);
        $principal = $fotos[1];

        $respuesta = $this->get(route('mascotas.public.show', $mascota))->assertOk();

        $respuesta->assertSee('x-data="galeriaFotos(', false)
            ->assertSee('<img src="'.Storage::disk('public')->url($principal->url).'" x-bind:src="fotos[actual].url" alt="Foto 1 de 3 de Luna"', false)
            ->assertSee('data-galeria="anterior"', false)
            ->assertSee('data-galeria="siguiente"', false)
            ->assertSee('1 / 3')
            ->assertSee('aria-label="Galería de fotos de Luna. Usa las flechas del teclado para cambiar de foto."', false);
        $this->assertSame(3, substr_count($respuesta->getContent(), 'data-galeria="miniatura"'));

        // Miniaturas en orden: la principal, luego las demás por orden; la primera, resaltada.
        $html = $respuesta->getContent();
        $posiciones = array_map(fn (FotoMascota $f) => strpos($html, 'src="'.$f->url_miniatura.'"'), [$fotos[1], $fotos[0], $fotos[2]]);
        $this->assertSame($posiciones, collect($posiciones)->sort()->values()->all());
        $this->assertStringContainsString('border-4 hover:opacity-100 focus:outline-none focus:ring-4 focus:ring-blue-300 border-amber-400"', $html);
    }

    public function test_con_una_sola_foto_no_hay_flechas_ni_miniaturas(): void
    {
        [$mascota] = $this->mascota(fotos: 1);

        $this->get(route('mascotas.public.show', $mascota))->assertOk()
            ->assertSee('data-galeria="principal"', false)
            ->assertSee('alt="Foto 1 de 1 de Luna"', false)
            ->assertDontSee('data-galeria="anterior"', false)
            ->assertDontSee('data-galeria="miniatura"', false)
            ->assertDontSee('1 / 1');
    }

    public function test_sin_fotos_se_mantiene_el_respaldo(): void
    {
        // Los triggers no permiten guardar una mascota disponible sin foto: se renderiza la vista con una sin guardar.
        $mascota = (new Mascota)->forceFill(['id' => 999999, 'nombre' => 'Luna', 'especie' => 'perro', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible'])
            ->setRelation('fotos', new Collection);

        $this->view('mascotas-publicas.show', ['mascota' => $mascota])
            ->assertSee('Sin fotografía disponible')
            ->assertDontSee('galeriaFotos', false);
    }

    public function test_las_miniaturas_usan_la_imagen_original_cuando_falta_la_miniatura(): void
    {
        [$mascota, $fotos] = $this->mascota(fotos: 2);
        // La segunda tiene miniatura; la primera es "antigua" (de las 35 pendientes).
        Storage::disk('public')->put(ImagenSegura::rutaMiniatura($fotos[1]->url), Img::jpeg(400, 400));

        $this->get(route('mascotas.public.show', $mascota))->assertOk()
            ->assertSee('<img src="'.Storage::disk('public')->url($fotos[0]->url).'" alt="" class="aspect-square', false)
            ->assertSee('<img src="'.Storage::disk('public')->url(ImagenSegura::rutaMiniatura($fotos[1]->url)).'" alt="" class="aspect-square', false);
    }

    /** @return array{0: Mascota, 1: list<FotoMascota>} Mascota disponible con $fotos fotos sin miniatura. */
    private function mascota(int $fotos, int $principal = 1): array
    {
        return DB::transaction(function () use ($fotos, $principal): array {
            $mascota = Mascota::create(['nombre' => 'Luna', 'especie' => 'perro', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $this->admin->id]);
            $creadas = [];

            foreach (range(1, $fotos) as $orden) {
                $ruta = "mascotas/{$mascota->id}/foto-{$orden}.jpg";
                Storage::disk('public')->put($ruta, Img::jpeg(500, 500));
                $creadas[] = FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => $ruta, 'es_principal' => $orden === $principal, 'orden' => $orden]);
            }

            return [$mascota, $creadas];
        });
    }
}
