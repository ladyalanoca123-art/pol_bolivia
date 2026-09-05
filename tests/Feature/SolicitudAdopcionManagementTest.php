<?php

namespace Tests\Feature;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\SolicitudAdopcion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolicitudAdopcionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_access_the_administrative_request_listing(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);

        $this->actingAs($administrator)
            ->get(route('admin.solicitudes-adopcion.index'))
            ->assertOk()
            ->assertSee('Solicitudes de adopción');
    }

    public function test_citizen_and_volunteer_cannot_access_administrative_requests(): void
    {
        foreach (['ciudadano', 'voluntario'] as $rol) {
            $this->actingAs(User::factory()->create(['rol' => $rol]))
                ->get(route('admin.solicitudes-adopcion.index'))
                ->assertForbidden();
        }
    }

    public function test_guest_is_redirected_from_protected_request_routes(): void
    {
        $mascota = $this->createAvailableMascota();

        $this->get(route('solicitudes-adopcion.create', $mascota))->assertRedirect(route('login'));
        $this->get(route('admin.solicitudes-adopcion.index'))->assertRedirect(route('login'));
    }

    public function test_citizen_can_create_a_pending_request_and_sensitive_input_is_ignored(): void
    {
        $citizen = User::factory()->create(['rol' => 'ciudadano']);
        $otherCitizen = User::factory()->create(['rol' => 'ciudadano']);
        $mascota = $this->createAvailableMascota(['nombre' => 'Luna']);
        $otherMascota = $this->createAvailableMascota(['nombre' => 'Nala']);

        $this->actingAs($citizen)
            ->post(route('solicitudes-adopcion.store', $mascota), [
                ...$this->requestData(),
                'solicitante_id' => $otherCitizen->id,
                'mascota_id' => $otherMascota->id,
                'estado' => 'aceptada',
            ])
            ->assertRedirect(route('solicitudes-adopcion.index'));

        $this->assertDatabaseHas('solicitudes_adopcion', [
            'solicitante_id' => $citizen->id,
            'mascota_id' => $mascota->id,
            'estado' => 'pendiente',
        ]);
        $this->assertDatabaseMissing('solicitudes_adopcion', ['mascota_id' => $otherMascota->id]);
    }

    public function test_citizen_cannot_request_a_pet_that_is_not_available(): void
    {
        $citizen = User::factory()->create(['rol' => 'ciudadano']);
        $mascota = $this->createMascota(['estado' => 'borrador']);

        $this->actingAs($citizen)
            ->from(route('solicitudes-adopcion.create', $this->createAvailableMascota()))
            ->post(route('solicitudes-adopcion.store', $mascota), $this->requestData())
            ->assertSessionHasErrors('mascota');

        $this->assertDatabaseMissing('solicitudes_adopcion', ['mascota_id' => $mascota->id]);
    }

    public function test_citizen_cannot_create_a_second_active_request_for_the_same_pet(): void
    {
        $citizen = User::factory()->create(['rol' => 'ciudadano']);
        $mascota = $this->createAvailableMascota();
        $this->createSolicitud($citizen, $mascota);

        $this->actingAs($citizen)
            ->post(route('solicitudes-adopcion.store', $mascota), $this->requestData())
            ->assertSessionHasErrors('mascota');
    }

    public function test_postgresql_also_rejects_duplicate_active_requests(): void
    {
        $citizen = User::factory()->create(['rol' => 'ciudadano']);
        $mascota = $this->createAvailableMascota();
        $this->createSolicitud($citizen, $mascota);

        $this->expectException(QueryException::class);

        $this->createSolicitud($citizen, $mascota);
    }

    public function test_citizen_can_view_only_their_own_requests(): void
    {
        $citizen = User::factory()->create(['rol' => 'ciudadano']);
        $otherCitizen = User::factory()->create(['rol' => 'ciudadano']);
        $own = $this->createSolicitud($citizen, $this->createAvailableMascota(['nombre' => 'Luna propia']));
        $other = $this->createSolicitud($otherCitizen, $this->createAvailableMascota(['nombre' => 'Nala ajena']));

        $this->actingAs($citizen)
            ->get(route('solicitudes-adopcion.index'))
            ->assertOk()
            ->assertSee('Luna propia')
            ->assertDontSee('Nala ajena');

        $this->actingAs($citizen)
            ->get(route('solicitudes-adopcion.show', $own))
            ->assertOk()
            ->assertSee('Luna propia');

        $this->actingAs($citizen)
            ->get(route('solicitudes-adopcion.show', $other))
            ->assertNotFound();
    }

    public function test_administrator_can_view_request_listing_and_detail(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $citizen = User::factory()->create(['rol' => 'ciudadano', 'nombres' => 'Wendy', 'apellidos' => 'Prueba']);
        $solicitud = $this->createSolicitud($citizen, $this->createAvailableMascota(['nombre' => 'Canela']));

        $this->actingAs($administrator)
            ->get(route('admin.solicitudes-adopcion.index', ['buscar' => 'Wendy']))
            ->assertOk()
            ->assertSee('Wendy')
            ->assertSee('Canela');

        $this->actingAs($administrator)
            ->get(route('admin.solicitudes-adopcion.show', $solicitud))
            ->assertOk()
            ->assertSee('Wendy Prueba')
            ->assertSee('Canela');
    }

    public function test_volunteer_cannot_create_an_adoption_request(): void
    {
        $volunteer = User::factory()->create(['rol' => 'voluntario']);
        $mascota = $this->createAvailableMascota();

        $this->actingAs($volunteer)
            ->post(route('solicitudes-adopcion.store', $mascota), $this->requestData())
            ->assertForbidden();
    }

    private function createSolicitud(User $citizen, Mascota $mascota): SolicitudAdopcion
    {
        return SolicitudAdopcion::create([
            ...$this->requestData(),
            'solicitante_id' => $citizen->id,
            'mascota_id' => $mascota->id,
            'estado' => 'pendiente',
        ]);
    }

    private function createAvailableMascota(array $attributes = []): Mascota
    {
        $mascota = $this->createMascota($attributes);

        FotoMascota::create([
            'mascota_id' => $mascota->id,
            'tipo' => 'foto',
            'url' => "mascotas/{$mascota->id}/principal.jpg",
            'es_principal' => true,
            'orden' => 1,
        ]);

        $mascota->update(['estado' => 'disponible']);

        return $mascota;
    }

    private function createMascota(array $attributes = []): Mascota
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);

        return Mascota::create(array_merge([
            'nombre' => fake()->unique()->firstName(),
            'especie' => 'perro',
            'raza' => 'Mestiza',
            'edad' => 3,
            'sexo' => 'hembra',
            'color' => 'Canela',
            'tamano' => 'mediano',
            'descripcion' => 'Mascota de prueba',
            'estado' => 'borrador',
            'registrado_por' => $administrator->id,
        ], $attributes));
    }

    private function requestData(): array
    {
        return [
            'tiene_otras_mascotas' => false,
            'viven_otras_personas' => true,
            'tiene_espacio_adecuado' => true,
            'zona_aproximada' => 'Centro',
            'horario_preferido' => 'Por las tardes',
            'comentarios_solicitante' => 'Puedo cuidar a la mascota.',
            'declara_informacion_veraz' => true,
        ];
    }
}
