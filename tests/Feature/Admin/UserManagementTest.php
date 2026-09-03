<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_administrator_can_access_user_management(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);

        $this->actingAs($administrator)
            ->get('/admin/usuarios')
            ->assertOk()
            ->assertSee('Gestión de usuarios')
            ->assertSee('Usuarios');
    }

    public function test_citizen_volunteer_and_inactive_administrator_cannot_access_user_management(): void
    {
        foreach ([
            ['rol' => 'ciudadano', 'estado' => true],
            ['rol' => 'voluntario', 'estado' => true],
            ['rol' => 'administrador', 'estado' => false],
        ] as $attributes) {
            $user = User::factory()->create($attributes);

            $this->actingAs($user)
                ->get('/admin/usuarios')
                ->assertForbidden();
        }
    }

    public function test_guest_is_redirected_to_login_from_user_management(): void
    {
        $this->get('/admin/usuarios')->assertRedirect(route('login'));
    }

    public function test_administrator_sees_only_basic_user_information(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $hash = Hash::make('secreto-no-visible');
        $user = User::factory()->create([
            'nombres' => 'Wendy',
            'apellidos' => 'Quispe',
            'email' => 'wendy@example.com',
            'telefono' => '70000000',
            'password' => $hash,
        ]);

        $this->actingAs($administrator)
            ->get('/admin/usuarios')
            ->assertOk()
            ->assertSee($user->nombres)
            ->assertSee($user->apellidos)
            ->assertSee($user->email)
            ->assertSee($user->telefono)
            ->assertDontSee($hash);
    }

    public function test_administrator_can_search_users_by_names(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $match = User::factory()->create(['nombres' => 'Wendy', 'apellidos' => 'Lopez']);
        $other = User::factory()->create(['nombres' => 'Marco', 'apellidos' => 'Rios']);

        $this->actingAs($administrator)
            ->get('/admin/usuarios?buscar=Wendy')
            ->assertSee($match->email)
            ->assertDontSee($other->email);
    }

    public function test_administrator_can_search_users_by_email(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $match = User::factory()->create(['email' => 'wendy.adopta@example.com']);
        $other = User::factory()->create(['email' => 'otro@example.com']);

        $this->actingAs($administrator)
            ->get('/admin/usuarios?buscar=wendy.adopta')
            ->assertSee($match->email)
            ->assertDontSee($other->email);
    }

    public function test_administrator_can_filter_users_by_role(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $volunteer = User::factory()->create(['rol' => 'voluntario']);
        $citizen = User::factory()->create(['rol' => 'ciudadano']);

        $this->actingAs($administrator)
            ->get('/admin/usuarios?rol=voluntario')
            ->assertSee($volunteer->email)
            ->assertDontSee($citizen->email);
    }

    public function test_administrator_can_filter_users_by_status(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $inactive = User::factory()->create(['estado' => false]);
        $active = User::factory()->create(['estado' => true]);

        $this->actingAs($administrator)
            ->get('/admin/usuarios?estado=inactivo')
            ->assertSee($inactive->email)
            ->assertDontSee($active->email);
    }

    public function test_administrator_can_deactivate_and_activate_a_citizen(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $citizen = User::factory()->create(['rol' => 'ciudadano', 'estado' => true]);

        $this->actingAs($administrator)
            ->patch(route('admin.usuarios.estado.update', $citizen), ['estado' => false])
            ->assertRedirect(route('admin.usuarios.index'));

        $this->assertDatabaseHas('usuarios', ['id' => $citizen->id, 'estado' => false]);

        $this->actingAs($administrator)
            ->patch(route('admin.usuarios.estado.update', $citizen), ['estado' => true])
            ->assertRedirect(route('admin.usuarios.index'));

        $this->assertDatabaseHas('usuarios', ['id' => $citizen->id, 'estado' => true]);
    }

    public function test_non_administrator_cannot_change_a_user_status(): void
    {
        $citizen = User::factory()->create(['rol' => 'ciudadano']);
        $target = User::factory()->create(['rol' => 'ciudadano', 'estado' => true]);

        $this->actingAs($citizen)
            ->patch(route('admin.usuarios.estado.update', $target), ['estado' => false])
            ->assertForbidden();

        $this->assertDatabaseHas('usuarios', ['id' => $target->id, 'estado' => true]);
    }

    public function test_status_action_cannot_change_a_role_to_administrator_or_volunteer(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $citizen = User::factory()->create(['rol' => 'ciudadano']);

        $this->actingAs($administrator)
            ->patch(route('admin.usuarios.estado.update', $citizen), [
                'estado' => false,
                'rol' => 'administrador',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('usuarios', [
            'id' => $citizen->id,
            'rol' => 'ciudadano',
            'estado' => false,
        ]);

        $this->actingAs($administrator)
            ->patch(route('admin.usuarios.estado.update', $citizen), [
                'estado' => true,
                'rol' => 'voluntario',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('usuarios', [
            'id' => $citizen->id,
            'rol' => 'ciudadano',
            'estado' => true,
        ]);
    }

    public function test_administrator_status_cannot_be_changed_from_this_screen(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $otherAdministrator = User::factory()->create(['rol' => 'administrador']);

        $this->actingAs($administrator)
            ->patch(route('admin.usuarios.estado.update', $otherAdministrator), ['estado' => false])
            ->assertForbidden();

        $this->assertDatabaseHas('usuarios', [
            'id' => $otherAdministrator->id,
            'estado' => true,
        ]);
    }

    public function test_volunteer_cannot_be_deactivated_from_this_endpoint(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $volunteer = User::factory()->create([
            'rol' => 'voluntario',
            'estado' => true,
        ]);

        $this->actingAs($administrator)
            ->patch(route('admin.usuarios.estado.update', $volunteer), ['estado' => false])
            ->assertForbidden();

        $this->assertDatabaseHas('usuarios', [
            'id' => $volunteer->id,
            'rol' => 'voluntario',
            'estado' => true,
        ]);
    }

    public function test_only_administrator_sees_users_navigation_link(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $citizen = User::factory()->create(['rol' => 'ciudadano']);
        $volunteer = User::factory()->create(['rol' => 'voluntario']);

        $this->actingAs($administrator)->get('/dashboard')->assertSee('Usuarios');
        $this->actingAs($citizen)->get('/dashboard')->assertDontSee('Usuarios');
        $this->actingAs($volunteer)->get('/dashboard')->assertDontSee('Usuarios');
    }
}
