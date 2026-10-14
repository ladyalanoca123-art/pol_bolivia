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
            // Bloques del panel operativo (resources/views/admin/dashboard.blade.php).
            ->assertSee('Solicitudes nuevas (7 días)')
            ->assertSee('En evaluación')
            ->assertSee('Seguimientos activos')
            ->assertSee('Incidencias abiertas')
            ->assertSee('Requiere tu atención')
            ->assertSee('Agenda de la semana')
            ->assertSee('Actividad reciente');

        $this->assertSame([
            'mascotas' => 0,
            'solicitudesPendientes' => 0,
            'solicitudesEnEvaluacion' => 0,
            'solicitudesRojas' => 0,
            'solicitudesAmarillas' => 0,
            'solicitudesVerdes' => 0,
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
