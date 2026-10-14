<?php

namespace Tests\Feature;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\PostulacionVoluntariado;
use App\Models\SeguimientoAdopcion;
use App\Models\SolicitudAdopcion;
use App\Models\User;
use App\Models\Voluntario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VoluntariadoManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_citizen_can_submit_a_pending_application_and_sensitive_input_is_ignored(): void
    {
        $citizen = User::factory()->create(['rol' => 'ciudadano']);
        $other = User::factory()->create(['rol' => 'ciudadano']);

        $this->actingAs($citizen)->post(route('postulaciones-voluntariado.store'), [
            ...$this->applicationData(), 'usuario_id' => $other->id, 'estado' => 'aprobada', 'revisado_por' => 1,
        ])->assertRedirect(route('postulaciones-voluntariado.index'));

        $this->assertDatabaseHas('postulaciones_voluntariado', ['usuario_id' => $citizen->id, 'estado' => 'pendiente']);
        $this->assertDatabaseMissing('postulaciones_voluntariado', ['usuario_id' => $other->id]);
    }

    public function test_citizen_cannot_submit_two_pending_applications(): void
    {
        $citizen = User::factory()->create(['rol' => 'ciudadano']);
        $this->makeApplication($citizen);

        $this->actingAs($citizen)->post(route('postulaciones-voluntariado.store'), $this->applicationData())
            ->assertSessionHasErrors('postulacion');
    }

    public function test_citizen_with_active_volunteer_profile_cannot_apply_again(): void
    {
        [$administrator, $citizen] = $this->approvedVolunteer();

        $this->actingAs($citizen)->post(route('postulaciones-voluntariado.store'), $this->applicationData())->assertForbidden();
    }

    public function test_only_administrator_can_manage_applications(): void
    {
        $application = $this->makeApplication(User::factory()->create(['rol' => 'ciudadano']));
        foreach (['ciudadano', 'voluntario'] as $role) {
            $actor = User::factory()->create(['rol' => $role]);
            $this->actingAs($actor)->get(route('admin.postulaciones-voluntariado.index'))->assertForbidden();
            $this->actingAs($actor)->patch(route('admin.postulaciones-voluntariado.approve', $application))->assertForbidden();
            $this->actingAs($actor)->patch(route('admin.postulaciones-voluntariado.reject', $application))->assertForbidden();
        }
    }

    public function test_administrator_can_approve_and_postgresql_creates_the_profile_and_promotes_role(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $citizen = User::factory()->create(['rol' => 'ciudadano']);
        $application = $this->makeApplication($citizen);

        $this->actingAs($administrator)->patch(route('admin.postulaciones-voluntariado.approve', $application), ['comentarios_admin' => 'Aprobada'])->assertRedirect();

        $this->assertDatabaseHas('postulaciones_voluntariado', ['id' => $application->id, 'estado' => 'aprobada', 'revisado_por' => $administrator->id]);
        $this->assertDatabaseHas('usuarios', ['id' => $citizen->id, 'rol' => 'voluntario']);
        $this->assertDatabaseHas('voluntarios', ['usuario_id' => $citizen->id, 'postulacion_id' => $application->id, 'estado' => 'activo']);
    }

    public function test_administrator_can_reject_and_a_processed_application_cannot_be_processed_again(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $application = $this->makeApplication(User::factory()->create(['rol' => 'ciudadano']));

        $this->actingAs($administrator)->patch(route('admin.postulaciones-voluntariado.reject', $application))->assertRedirect();
        $this->actingAs($administrator)->patch(route('admin.postulaciones-voluntariado.approve', $application))->assertForbidden();
        $this->assertDatabaseHas('postulaciones_voluntariado', ['id' => $application->id, 'estado' => 'rechazada']);
    }

    public function test_administrator_can_deactivate_a_volunteer_and_the_user_returns_to_citizen(): void
    {
        [$administrator, $volunteer] = $this->approvedVolunteer();
        $profile = Voluntario::query()->where('usuario_id', $volunteer->id)->sole();

        $this->actingAs($administrator)->patch(route('admin.voluntarios.deactivate', $profile))->assertRedirect();
        $this->assertDatabaseHas('voluntarios', ['id' => $profile->id, 'estado' => 'inactivo']);
        $this->assertDatabaseHas('usuarios', ['id' => $volunteer->id, 'rol' => 'ciudadano']);
    }

    public function test_approving_a_new_application_reactivates_an_existing_inactive_volunteer_profile(): void
    {
        [$administrator, $volunteer] = $this->approvedVolunteer();
        $profile = Voluntario::query()->where('usuario_id', $volunteer->id)->sole();
        $this->actingAs($administrator)->patch(route('admin.voluntarios.deactivate', $profile))->assertRedirect();

        $this->actingAs($volunteer->fresh())->post(route('postulaciones-voluntariado.store'), $this->applicationData())->assertRedirect();
        $newApplication = PostulacionVoluntariado::query()->where('usuario_id', $volunteer->id)->orderByDesc('id')->firstOrFail();
        $this->actingAs($administrator)->patch(route('admin.postulaciones-voluntariado.approve', $newApplication))->assertRedirect();

        $this->assertDatabaseHas('voluntarios', ['id' => $profile->id, 'postulacion_id' => $newApplication->id, 'estado' => 'activo']);
        $this->assertDatabaseHas('usuarios', ['id' => $volunteer->id, 'rol' => 'voluntario']);
    }

    public function test_administrator_can_list_and_view_volunteers_while_citizen_can_only_view_own_application(): void
    {
        [$administrator, $volunteer] = $this->approvedVolunteer();
        $profile = Voluntario::query()->where('usuario_id', $volunteer->id)->sole();
        $citizen = User::factory()->create(['rol' => 'ciudadano']);
        $ownApplication = $this->makeApplication($citizen);
        $otherApplication = $this->makeApplication(User::factory()->create(['rol' => 'ciudadano']));

        $this->actingAs($administrator)->get(route('admin.voluntarios.index'))->assertOk()->assertSee($volunteer->nombres);
        $this->actingAs($administrator)->get(route('admin.voluntarios.show', $profile))->assertOk()->assertSee($volunteer->email);
        $this->actingAs($citizen)->get(route('postulaciones-voluntariado.show', $ownApplication))->assertOk();
        $this->actingAs($citizen)->get(route('postulaciones-voluntariado.show', $otherApplication))->assertNotFound();
    }

    public function test_administrator_cannot_deactivate_a_volunteer_with_an_active_tracking_case(): void
    {
        [$administrator, $volunteer] = $this->approvedVolunteer();
        $profile = Voluntario::query()->where('usuario_id', $volunteer->id)->sole();
        $tracking = $this->trackingFor($volunteer);
        $tracking->update(['voluntario_id' => $volunteer->id, 'estado' => 'activo', 'fecha_inicio' => now()]);

        $this->actingAs($administrator)->patch(route('admin.voluntarios.deactivate', $profile))->assertForbidden();
        $this->assertDatabaseHas('voluntarios', ['id' => $profile->id, 'estado' => 'activo']);
    }

    public function test_inactive_users_cannot_access_volunteer_operations(): void
    {
        $inactiveCitizen = User::factory()->create(['rol' => 'ciudadano', 'estado' => false]);
        $inactiveAdministrator = User::factory()->create(['rol' => 'administrador', 'estado' => false]);

        $this->actingAs($inactiveCitizen)->get(route('postulaciones-voluntariado.create'))->assertForbidden();
        $this->actingAs($inactiveAdministrator)->get(route('admin.voluntarios.index'))->assertForbidden();
    }

    private function approvedVolunteer(): array
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $citizen = User::factory()->create(['rol' => 'ciudadano']);
        $application = $this->makeApplication($citizen);
        $this->actingAs($administrator)->patch(route('admin.postulaciones-voluntariado.approve', $application));

        return [$administrator, $citizen->fresh()];
    }

    private function makeApplication(User $citizen): PostulacionVoluntariado
    {
        return PostulacionVoluntariado::create([...\Tests\Support\CuestionarioVoluntariadoDatos::registro(), 'usuario_id' => $citizen->id, 'estado' => 'pendiente']);
    }

    /** Datos que envía el formulario del cuestionario V1. */
    private function applicationData(): array
    {
        return \Tests\Support\CuestionarioVoluntariadoDatos::valido();
    }

    private function trackingFor(User $volunteer): SeguimientoAdopcion
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $citizen = User::factory()->create(['rol' => 'ciudadano']);
        $pet = DB::transaction(function () use ($administrator): Mascota {
            $pet = Mascota::create(['nombre' => fake()->unique()->firstName(), 'especie' => 'perro', 'raza' => 'Mestiza', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $administrator->id]);
            FotoMascota::create(['mascota_id' => $pet->id, 'tipo' => 'foto', 'url' => 'mascotas/principal.jpg', 'es_principal' => true, 'orden' => 1]);
            return $pet;
        });
        $request = SolicitudAdopcion::create(['solicitante_id' => $citizen->id, 'mascota_id' => $pet->id, ...\Tests\Support\CuestionarioAdopcionDatos::registro(), 'estado' => 'pendiente']);
        $request->update(['estado' => 'aceptada']);

        return SeguimientoAdopcion::query()->where('solicitud_id', $request->id)->sole();
    }
}
