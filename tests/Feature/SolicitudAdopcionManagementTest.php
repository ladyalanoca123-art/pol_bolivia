<?php

namespace Tests\Feature;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\SeguimientoAdopcion;
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

    public function test_administrator_can_accept_a_valid_request(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $mascota = $this->createAvailableMascota();
        $solicitud = $this->createSolicitud(
            User::factory()->create(['rol' => 'ciudadano']),
            $mascota,
        );

        $this->actingAs($administrator)
            ->patch(route('admin.solicitudes-adopcion.accept', $solicitud))
            ->assertRedirect(route('admin.solicitudes-adopcion.show', $solicitud));

        $this->assertDatabaseHas('solicitudes_adopcion', [
            'id' => $solicitud->id,
            'estado' => 'aceptada',
        ]);
        $this->assertDatabaseHas('mascotas', [
            'id' => $mascota->id,
            'estado' => 'adoptada',
        ]);

        $seguimiento = SeguimientoAdopcion::query()
            ->where('solicitud_id', $solicitud->id)
            ->sole();

        $this->assertSame('sin_asignar', $seguimiento->estado);
        $this->assertSame($solicitud->zona_aproximada, $seguimiento->zona_aproximada);
        $this->assertSame($solicitud->horario_preferido, $seguimiento->horario_preferido);
        $this->assertNull($seguimiento->voluntario_id);
    }

    public function test_accepting_a_request_closes_all_other_active_requests_for_the_same_pet(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $mascota = $this->createAvailableMascota();
        $accepted = $this->createSolicitud(User::factory()->create(['rol' => 'ciudadano']), $mascota);
        $pending = $this->createSolicitud(User::factory()->create(['rol' => 'ciudadano']), $mascota);
        $waiting = $this->createSolicitud(User::factory()->create(['rol' => 'ciudadano']), $mascota);
        $waiting->update(['estado' => 'en_espera']);

        $this->actingAs($administrator)
            ->patch(route('admin.solicitudes-adopcion.accept', $accepted))
            ->assertRedirect();

        $this->assertDatabaseHas('solicitudes_adopcion', ['id' => $accepted->id, 'estado' => 'aceptada']);
        $this->assertDatabaseHas('solicitudes_adopcion', ['id' => $pending->id, 'estado' => 'cerrada']);
        $this->assertDatabaseHas('solicitudes_adopcion', ['id' => $waiting->id, 'estado' => 'cerrada']);
        $this->assertSame(1, SolicitudAdopcion::query()
            ->where('mascota_id', $mascota->id)
            ->where('estado', 'aceptada')
            ->count());
        $this->assertSame(1, SeguimientoAdopcion::query()
            ->where('solicitud_id', $accepted->id)
            ->count());
    }

    public function test_administrator_can_reject_a_valid_request(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $mascota = $this->createAvailableMascota();
        $solicitud = $this->createSolicitud(
            User::factory()->create(['rol' => 'ciudadano']),
            $mascota,
        );
        $otherSolicitud = $this->createSolicitud(User::factory()->create(['rol' => 'ciudadano']), $mascota);

        $this->actingAs($administrator)
            ->patch(route('admin.solicitudes-adopcion.reject', $solicitud))
            ->assertRedirect(route('admin.solicitudes-adopcion.show', $solicitud));

        $this->assertDatabaseHas('solicitudes_adopcion', [
            'id' => $solicitud->id,
            'estado' => 'rechazada',
        ]);
        $this->assertDatabaseHas('mascotas', ['id' => $mascota->id, 'estado' => 'disponible']);
        $this->assertDatabaseHas('solicitudes_adopcion', ['id' => $otherSolicitud->id, 'estado' => 'pendiente']);
        $this->assertDatabaseMissing('seguimientos_adopcion', ['solicitud_id' => $solicitud->id]);
    }

    public function test_citizen_and_volunteer_cannot_accept_or_reject_requests(): void
    {
        $solicitud = $this->createSolicitud(
            User::factory()->create(['rol' => 'ciudadano']),
            $this->createAvailableMascota(),
        );

        foreach (['ciudadano', 'voluntario'] as $rol) {
            $actor = User::factory()->create(['rol' => $rol]);

            $this->actingAs($actor)
                ->patch(route('admin.solicitudes-adopcion.accept', $solicitud))
                ->assertForbidden();

            $this->actingAs($actor)
                ->patch(route('admin.solicitudes-adopcion.reject', $solicitud))
                ->assertForbidden();
        }

        $this->assertDatabaseHas('solicitudes_adopcion', ['id' => $solicitud->id, 'estado' => 'pendiente']);
    }

    public function test_a_final_request_cannot_be_processed_again(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $accepted = $this->createSolicitud(
            User::factory()->create(['rol' => 'ciudadano']),
            $this->createAvailableMascota(),
        );

        $this->actingAs($administrator)
            ->patch(route('admin.solicitudes-adopcion.accept', $accepted))
            ->assertRedirect();

        $this->actingAs($administrator)
            ->patch(route('admin.solicitudes-adopcion.accept', $accepted))
            ->assertForbidden();

        $rejected = $this->createSolicitud(
            User::factory()->create(['rol' => 'ciudadano']),
            $this->createAvailableMascota(),
        );

        $this->actingAs($administrator)
            ->patch(route('admin.solicitudes-adopcion.reject', $rejected))
            ->assertRedirect();

        $this->actingAs($administrator)
            ->patch(route('admin.solicitudes-adopcion.reject', $rejected))
            ->assertForbidden();
    }

    public function test_state_from_the_client_is_ignored_by_explicit_administrative_actions(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $solicitud = $this->createSolicitud(
            User::factory()->create(['rol' => 'ciudadano']),
            $this->createAvailableMascota(),
        );

        $this->actingAs($administrator)
            ->patch(route('admin.solicitudes-adopcion.accept', $solicitud), [
                'estado' => 'rechazada',
                'solicitante_id' => User::factory()->create(['rol' => 'ciudadano'])->id,
                'mascota_id' => $this->createAvailableMascota()->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('solicitudes_adopcion', [
            'id' => $solicitud->id,
            'estado' => 'aceptada',
        ]);
    }

    public function test_administrator_cannot_accept_a_request_when_its_pet_is_no_longer_available(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $mascota = $this->createAvailableMascota();
        $solicitud = $this->createSolicitud(User::factory()->create(['rol' => 'ciudadano']), $mascota);
        $mascota->update(['estado' => 'borrador']);

        $this->actingAs($administrator)
            ->patch(route('admin.solicitudes-adopcion.accept', $solicitud))
            ->assertForbidden();

        $this->assertDatabaseHas('solicitudes_adopcion', [
            'id' => $solicitud->id,
            'estado' => 'pendiente',
        ]);
    }

    public function test_administrative_detail_only_shows_actions_for_a_request_that_can_be_processed(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $solicitud = $this->createSolicitud(
            User::factory()->create(['rol' => 'ciudadano']),
            $this->createAvailableMascota(),
        );

        $this->actingAs($administrator)
            ->get(route('admin.solicitudes-adopcion.show', $solicitud))
            ->assertSee('Aceptar')
            ->assertSee('Rechazar');

        $solicitud->update(['estado' => 'rechazada']);

        $this->actingAs($administrator)
            ->get(route('admin.solicitudes-adopcion.show', $solicitud))
            ->assertDontSee('Aceptar')
            ->assertDontSee('Rechazar');
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
