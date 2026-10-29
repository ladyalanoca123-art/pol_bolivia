<?php

namespace Tests\Feature;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\SolicitudAdopcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CompletaFlujoAdopcion;
use Tests\TestCase;

class CatalogoMascotaPublicoTest extends TestCase
{
    use RefreshDatabase;
    use CompletaFlujoAdopcion;

    public function test_home_only_shows_available_pets_and_existing_module_links(): void
    {
        $available = $this->createPet(['nombre' => 'Molly']);
        $adopted = $this->createPet(['nombre' => 'Nala']);
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $solicitud = SolicitudAdopcion::create([
            'solicitante_id' => User::factory()->create(['rol' => 'ciudadano'])->id,
            'mascota_id' => $adopted->id,
            ...\Tests\Support\CuestionarioAdopcionDatos::registro(),
            'estado' => 'pendiente',
        ]);
        $this->completarAdopcionPorRutas($administrator, $solicitud);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Molly')
            ->assertDontSee('Nala')
            ->assertSee(route('mascotas.public.show', $available), false)
            // La sesión es del administrador: ENVIAR SOLICITUD abre el aviso explicativo (no es ciudadano).
            ->assertSee('ENVIAR SOLICITUD')
            ->assertSee('abrir-aviso-adopcion', false)
            ->assertSee(route('postulaciones-voluntariado.create'), false);

        $this->get(route('mascotas.catalogo'))
            ->assertOk()
            ->assertSee('Mascotas que esperan tu cariño')
            ->assertSee('Conoce a quienes buscan un hogar en Cochabamba.')
            ->assertSee('Molly')
            ->assertDontSee('Nala');

        $this->get(route('mascotas.public.show', $adopted))->assertNotFound();
    }

    public function test_public_pet_profile_shows_real_data_and_request_link(): void
    {
        $mascota = $this->createPet([
            'nombre' => 'Luna',
            'edad' => 7,
            'edad_en' => 'meses',
            'sexo' => 'hembra',
            'tamano' => 'pequeno',
            'raza' => 'Mestiza',
            'color' => 'Blanco',
            'descripcion' => 'Descripción real de Luna.',
        ]);

        $this->get(route('mascotas.public.show', $mascota))
            ->assertOk()
            ->assertSee('Luna')
            ->assertSee('7 meses')
            ->assertSee('Hembra')
            ->assertSee('Pequeno')
            ->assertSee('Descripción real de Luna.')
            // Visitante: ENVIAR SOLICITUD abre el aviso; sin JavaScript lleva al acceso de esta mascota.
            ->assertSee(route('mascotas.solicitar-acceso', [$mascota, 'inicio']), false);
    }

    public function test_guest_keeps_selected_pet_context_through_existing_login_flow(): void
    {
        $mascota = $this->createPet();
        $url = route('solicitudes-adopcion.create', $mascota);

        $this->get($url)
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', $url);
    }

    public function test_guest_is_redirected_to_login_before_existing_volunteer_application_flow(): void
    {
        $url = route('postulaciones-voluntariado.create');

        $this->get($url)
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', $url);
    }

    public function test_home_shows_at_most_seven_available_pets_and_catalog_filters_by_species(): void
    {
        foreach (range(1, 8) as $number) {
            $this->createPet(['nombre' => "Perro {$number}", 'especie' => 'perro']);
        }
        $this->createPet(['nombre' => 'Gata visible', 'especie' => 'gato']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Perro 1')
            ->assertDontSee('Perro 8')
            ->assertSee('EXPLORAR CATÁLOGO');

        $this->get(route('mascotas.catalogo', ['especie' => 'perro']))
            ->assertOk()
            ->assertSee('Perritos en adopción')
            ->assertSee('Encuentra a tu compañero fiel en Cochabamba.')
            ->assertSee('Perro 1')
            ->assertDontSee('Gata visible');

        $this->get(route('mascotas.catalogo', ['especie' => 'gato']))
            ->assertOk()
            ->assertSee('Gatitos en adopción')
            ->assertSee('Pequeñas patitas, grandes historias. ¡Conoce a tu nuevo compañero!')
            ->assertSee('Gata visible')
            ->assertDontSee('Perro 1');
    }

    private function createPet(array $attributes = []): Mascota
    {
        return DB::transaction(function () use ($attributes): Mascota {
            $mascota = Mascota::create(array_merge([
                'nombre' => fake()->unique()->firstName(),
                'especie' => 'perro',
                'raza' => 'Mestiza',
                'edad' => 3,
                'edad_en' => 'años',
                'sexo' => 'hembra',
                'color' => 'Canela',
                'tamano' => 'mediano',
                'descripcion' => 'Mascota de prueba',
                'estado' => 'disponible',
                'registrado_por' => User::factory()->create(['rol' => 'administrador'])->id,
            ], $attributes));

            FotoMascota::create([
                'mascota_id' => $mascota->id,
                'tipo' => 'foto',
                'url' => "mascotas/{$mascota->id}/principal.jpg",
                'es_principal' => true,
                'orden' => 1,
            ]);

            return $mascota;
        });
    }
}
