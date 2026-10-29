<?php

namespace Tests\Feature;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\SolicitudAdopcion;
use App\Models\User;
use App\Rules\TelefonoBoliviano;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CuestionarioAdopcionDatos as Datos;
use Tests\TestCase;

/**
 * El teléfono es opcional al crear la cuenta, pero obligatorio para solicitar una adopción: la administración y el
 * voluntario del seguimiento contactan por WhatsApp. Se guarda en «Mi perfil», no entre las respuestas.
 */
class TelefonoSolicitudAdopcionTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_formulario_pide_el_telefono_y_lo_trae_del_perfil(): void
    {
        $conTelefono = User::factory()->create(['rol' => 'ciudadano', 'telefono' => '76543210']);
        $sinTelefono = User::factory()->create(['rol' => 'ciudadano', 'telefono' => null]);
        $mascota = $this->mascota();

        $this->actingAs($conTelefono)->get(route('solicitudes-adopcion.create', $mascota))->assertOk()
            ->assertSee('Teléfono de contacto (WhatsApp)')
            ->assertSee('name="telefono_contacto"', false)
            ->assertSee('value="76543210"', false);

        $this->actingAs($sinTelefono)->get(route('solicitudes-adopcion.create', $mascota))->assertOk()
            ->assertSee('id="telefono_contacto" maxlength="20" required value=""', false);
    }

    public function test_sin_telefono_no_se_puede_enviar_la_solicitud(): void
    {
        $ciudadano = User::factory()->create(['rol' => 'ciudadano', 'telefono' => null]);

        $this->actingAs($ciudadano)
            ->post(route('solicitudes-adopcion.store', $this->mascota()), Datos::valido(['telefono_contacto' => '  ']))
            ->assertSessionHasErrors(['telefono_contacto' => 'Escribe un teléfono con WhatsApp para que la fundación pueda coordinar contigo.']);

        $this->assertSame(0, SolicitudAdopcion::count());
        $this->assertNull($ciudadano->refresh()->telefono);
    }

    public function test_un_telefono_invalido_se_rechaza_con_el_mismo_mensaje_del_registro(): void
    {
        $ciudadano = User::factory()->create(['rol' => 'ciudadano', 'telefono' => null]);

        foreach (['2345678', '81234567', '7123456', 'abc'] as $invalido) {
            $this->actingAs($ciudadano)
                ->post(route('solicitudes-adopcion.store', $this->mascota()), Datos::valido(['telefono_contacto' => $invalido]))
                ->assertSessionHasErrors(['telefono_contacto' => TelefonoBoliviano::MENSAJE]);
        }

        $this->assertSame(0, SolicitudAdopcion::count());
    }

    public function test_el_telefono_se_normaliza_y_se_guarda_en_el_perfil_no_en_las_respuestas(): void
    {
        $ciudadano = User::factory()->create(['rol' => 'ciudadano', 'telefono' => null]);

        $this->actingAs($ciudadano)
            ->post(route('solicitudes-adopcion.store', $this->mascota()), Datos::valido(['telefono_contacto' => '+591 712-34567']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('solicitudes-adopcion.index'));

        $this->assertSame('71234567', $ciudadano->refresh()->telefono);
        $solicitud = SolicitudAdopcion::where('solicitante_id', $ciudadano->id)->sole();
        $this->assertArrayNotHasKey('telefono_contacto', $solicitud->respuestas);
    }

    public function test_si_cambia_el_telefono_al_solicitar_se_actualiza_su_perfil(): void
    {
        $ciudadano = User::factory()->create(['rol' => 'ciudadano', 'telefono' => '60000001']);

        $this->actingAs($ciudadano)
            ->post(route('solicitudes-adopcion.store', $this->mascota()), Datos::valido(['telefono_contacto' => '79998877']))
            ->assertSessionHasNoErrors();

        $this->assertSame('79998877', $ciudadano->refresh()->telefono);
    }

    public function test_con_el_telefono_de_la_solicitud_el_administrador_ve_el_boton_de_whatsapp(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $ciudadano = User::factory()->create(['rol' => 'ciudadano', 'telefono' => null]);
        $this->actingAs($ciudadano)
            ->post(route('solicitudes-adopcion.store', $this->mascota()), Datos::valido(['telefono_contacto' => '71234567']))
            ->assertSessionHasNoErrors();
        $solicitud = SolicitudAdopcion::where('solicitante_id', $ciudadano->id)->sole();

        $this->actingAs($admin)->patch(route('admin.solicitudes-adopcion.evaluate', $solicitud))->assertRedirect();

        $this->actingAs($admin)->get(route('admin.solicitudes-adopcion.show', $solicitud))
            ->assertSee('https://wa.me/59171234567?text=', false)
            ->assertDontSee('tel:+591', false)                     // sin botón «Llamar»
            ->assertDontSee('El solicitante no tiene un teléfono válido registrado.');
    }

    private function mascota(): Mascota
    {
        $admin = User::factory()->create(['rol' => 'administrador']);

        return DB::transaction(function () use ($admin): Mascota {
            $mascota = Mascota::create([
                'nombre' => fake()->unique()->firstName(), 'especie' => 'perro', 'edad' => 2, 'edad_en' => 'años',
                'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $admin->id,
            ]);
            FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => "mascotas/{$mascota->id}/principal.jpg", 'es_principal' => true, 'orden' => 1]);

            return $mascota;
        });
    }
}
