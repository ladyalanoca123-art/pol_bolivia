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

        $this->actingAs($administrator)
            ->get('/admin/panel')
            ->assertOk();
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

    public function test_citizen_can_access_dashboard_and_profile_without_administration_link(): void
    {
        $citizen = User::factory()->create(['rol' => 'ciudadano']);

        $this->actingAs($citizen)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Panel de administración');

        $this->actingAs($citizen)
            ->get('/profile')
            ->assertOk();
    }

    public function test_administrator_sees_the_administration_link(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);

        $this->actingAs($administrator)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Panel de administración');
    }

    public function test_volunteer_does_not_see_the_administration_link(): void
    {
        $volunteer = User::factory()->create(['rol' => 'voluntario']);

        $this->actingAs($volunteer)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Panel de administración');
    }
}
