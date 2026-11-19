<?php

namespace Tests\Feature;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\SolicitudAdopcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CuestionarioAdopcionDatos;
use Tests\TestCase;

/**
 * Fila «Te esperan hace más tiempo» del catálogo público: disponibles con 45 días o más y sin solicitudes en
 * trámite, primero las que más esperan, con botón para compartir por WhatsApp y sin repetirse en la cuadrícula.
 */
class CatalogoEsperanHogarTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_fila_muestra_a_las_que_esperan_hace_mas_tiempo_sin_solicitudes(): void
    {
        $this->assertSame('pol_bolivia_test', DB::connection()->getDatabaseName());
        $this->mascota('Anciana', 80);
        $this->mascota('Veterana', 50);
        $this->mascota('Novata', 10);
        $conSolicitud = $this->mascota('Pedida', 90);
        SolicitudAdopcion::create([
            ...CuestionarioAdopcionDatos::registro(),
            'solicitante_id' => User::factory()->create(['rol' => 'ciudadano'])->id,
            'mascota_id' => $conSolicitud->id,
            'estado' => 'pendiente',
        ]);

        foreach ([route('home'), route('mascotas.catalogo')] as $url) {
            $html = $this->get($url)->assertOk()
                ->assertSee('Te esperan hace más tiempo')
                ->assertSeeInOrder(['Anciana', 'Espera hace 80 días', 'COMPARTIR', 'Veterana', 'Espera hace 50 días'])
                ->assertSee('https://wa.me/?text=', false)
                ->getContent();

            $fila = $this->fila($html);
            $this->assertStringNotContainsString('Novata', $fila, $url);
            $this->assertStringNotContainsString('Pedida', $fila, $url);
            // No se repiten en la cuadrícula de abajo.
            $this->assertSame(1, substr_count($html, '>Anciana</h3>'), $url);
            $this->assertStringContainsString('>Novata</h3>', $html);
        }
    }

    public function test_respeta_el_filtro_de_especie_y_no_aparece_si_no_hay_ninguna(): void
    {
        $this->mascota('Michi', 60, 'gato');
        $this->mascota('Firulais', 60, 'perro');

        $this->get(route('mascotas.catalogo', ['especie' => 'gato']))->assertOk()
            ->assertSee('Espera hace 60 días')->assertSee('Michi')->assertDontSee('Firulais');

        DB::table('mascotas')->update(['created_at' => now()->subDays(5)]);
        $this->get(route('home'))->assertOk()
            ->assertDontSee('Te esperan hace más tiempo')
            ->assertDontSee('https://wa.me/?text=', false)
            ->assertSee('Michi');
    }

    private function fila(string $html): string
    {
        $inicio = strpos($html, 'id="titulo-esperan-hogar"');
        $this->assertNotFalse($inicio);

        // La fila termina donde empieza la cuadrícula normal.
        $fin = strpos($html, 'grid grid-cols-2 gap-4', $inicio);
        $this->assertNotFalse($fin);

        return substr($html, $inicio, $fin - $inicio);
    }

    private function mascota(string $nombre, int $dias, string $especie = 'perro'): Mascota
    {
        return DB::transaction(function () use ($nombre, $dias, $especie): Mascota {
            $mascota = Mascota::create([
                'nombre' => $nombre, 'especie' => $especie, 'edad' => 3, 'edad_en' => 'años', 'sexo' => 'hembra',
                'tamano' => 'mediano', 'estado' => 'disponible',
                'registrado_por' => User::factory()->create(['rol' => 'administrador'])->id,
            ]);
            FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => "mascotas/{$mascota->id}/p.jpg", 'es_principal' => true, 'orden' => 1]);
            DB::table('mascotas')->where('id', $mascota->id)->update(['created_at' => now()->subDays($dias)]);

            return $mascota->refresh();
        });
    }
}
