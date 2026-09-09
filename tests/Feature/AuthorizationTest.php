<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_access_the_administration_panel(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);

        $response = $this->actingAs($administrator)
            ->get('/admin/panel')
            ->assertOk()
            ->assertSee('Panel principal')
            ->assertSee('Total de mascotas registradas')
            ->assertSee('Solicitudes de adopción pendientes')
            ->assertSee('Adopciones aprobadas')
            ->assertSee('Usuarios registrados')
            ->assertSee('Voluntarios activos')
            ->assertSee('Postulaciones pendientes')
            ->assertSee('Incidencias pendientes');

        $this->assertSame([
            'mascotas' => 0,
            'solicitudesPendientes' => 0,
            'adopcionesAprobadas' => 0,
            'usuarios' => 1,
            'voluntariosActivos' => 0,
            'postulacionesPendientes' => 0,
            'incidenciasPendientes' => 0,
        ], $response->viewData('indicadores'));
    }

    public function test_citizen_cannot_access_the_administration_panel(): void
    {
        $citizen = User::factory()->create(['rol' => 'ciudadano']);

        $this->actingAs($citizen)
            ->get('/admin/panel')
            ->assertForbidden();
    }

    public function test_volunteer_cannot_access_the_administration_panel(): void
    {
        $volunteer = User::factory()->create(['rol' => 'voluntario']);

        $this->actingAs($volunteer)
            ->get('/admin/panel')
            ->assertForbidden();
    }

    public function test_inactive_administrator_cannot_access_the_administration_panel(): void
    {
        $administrator = User::factory()->create([
            'rol' => 'administrador',
            'estado' => false,
        ]);

        $this->actingAs($administrator)
            ->get('/admin/panel')
            ->assertForbidden();
    }

    public function test_guest_cannot_access_protected_routes(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/profile')->assertRedirect(route('login'));
        $this->get('/admin/panel')->assertRedirect(route('login'));
    }

    public function test_citizen_dashboard_redirects_to_profile(): void
    {
        $citizen = User::factory()->create(['rol' => 'ciudadano']);

        $this->actingAs($citizen)
            ->get('/dashboard')
            ->assertRedirect(route('profile.edit'));

        $this->actingAs($citizen)
            ->get('/profile')
            ->assertOk();
    }

    public function test_administrator_dashboard_redirects_to_main_panel(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);

        $this->actingAs($administrator)
            ->get('/dashboard')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_volunteer_dashboard_redirects_to_profile(): void
    {
        $volunteer = User::factory()->create(['rol' => 'voluntario']);

        $this->actingAs($volunteer)
            ->get('/dashboard')
            ->assertRedirect(route('profile.edit'));
    }
}
