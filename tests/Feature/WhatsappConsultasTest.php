<?php

namespace Tests\Feature;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Botón flotante de WhatsApp para consultas en las páginas públicas (x-whatsapp-flotante).
 */
class WhatsappConsultasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Número de prueba: no depende del configurado para la fundación.
        config(['pol.whatsapp.consultas' => '71234567']);
    }

    public function test_las_paginas_publicas_muestran_el_boton_con_un_mensaje_general(): void
    {
        $enlace = 'https://wa.me/59171234567?text='.rawurlencode('Hola POL-Bolivia, quisiera hacer una consulta.');

        $this->get(route('home'))->assertOk()->assertSee($enlace, false)->assertSee('Escribir a POL-Bolivia por WhatsApp');
        $this->get(route('mascotas.catalogo'))->assertOk()->assertSee($enlace, false);
    }

    public function test_en_la_ficha_de_una_mascota_el_mensaje_lleva_su_nombre(): void
    {
        $mascota = $this->mascota('Canela');

        $this->get(route('mascotas.public.show', $mascota))->assertOk()
            ->assertSee('https://wa.me/59171234567?text='.rawurlencode('Hola POL-Bolivia, quisiera consultar sobre Canela.'), false);
    }

    public function test_sin_numero_valido_no_aparece(): void
    {
        config(['pol.whatsapp.consultas' => '']);
        $this->get(route('home'))->assertOk()->assertDontSee('Escribir a POL-Bolivia por WhatsApp');

        config(['pol.whatsapp.consultas' => '123']);
        $this->get(route('home'))->assertOk()->assertDontSee('Escribir a POL-Bolivia por WhatsApp');
    }

    public function test_no_aparece_en_el_panel_ni_en_mi_espacio(): void
    {
        $this->actingAs(User::factory()->create(['rol' => 'administrador']))
            ->get(route('admin.dashboard'))->assertOk()->assertDontSee('Escribir a POL-Bolivia por WhatsApp');
        $this->actingAs(User::factory()->create(['rol' => 'ciudadano']))
            ->get(route('inicio'))->assertOk()->assertDontSee('Escribir a POL-Bolivia por WhatsApp');
    }

    public function test_el_menu_publico_lleva_a_mi_espacio(): void
    {
        $this->actingAs(User::factory()->create(['rol' => 'ciudadano']))
            ->get(route('home'))->assertOk()->assertSee('Mi espacio')->assertSee(route('inicio'), false);
    }

    public function test_el_pie_de_pagina_muestra_las_redes_y_el_contacto_de_pol(): void
    {
        $respuesta = $this->get(route('home'))->assertOk()
            ->assertSee('Cochabamba, Bolivia')->assertSee(config('pol.contacto.correo'))
            ->assertSee('+591 7123 4567')->assertSee('Nuestro compromiso')->assertSee('Juntos por un futuro mejor');

        foreach (config('pol.redes') as $url) {
            $respuesta->assertSee($url, false);
        }
    }

    private function mascota(string $nombre): Mascota
    {
        $admin = User::factory()->create(['rol' => 'administrador']);

        return DB::transaction(function () use ($nombre, $admin): Mascota {
            $mascota = Mascota::create(['nombre' => $nombre, 'especie' => 'perro', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $admin->id]);
            FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => 'test.jpg', 'es_principal' => true, 'orden' => 1]);

            return $mascota;
        });
    }
}
