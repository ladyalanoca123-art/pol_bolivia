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

    public function test_administrator_can_search_users_by_last_name_and_combine_filters(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $match = User::factory()->create(['nombres' => 'Laura', 'apellidos' => 'Mamani', 'rol' => 'ciudadano', 'estado' => true]);
        $other = User::factory()->create(['nombres' => 'Laura', 'apellidos' => 'Mamani', 'rol' => 'ciudadano', 'estado' => false]);

        $this->actingAs($administrator)
            ->get('/admin/usuarios?buscar=Mamani&rol=ciudadano&estado=activo')
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

    public function test_administrator_can_view_and_edit_basic_user_information_without_exposing_password(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $hash = Hash::make('secreto-no-visible');
        $user = User::factory()->create(['password' => $hash, 'email' => 'original@example.com']);

        $this->actingAs($administrator)
            ->get(route('admin.usuarios.show', $user))
            ->assertOk()
            ->assertSee($user->email)
            ->assertDontSee($hash);

        $this->actingAs($administrator)
            ->get(route('admin.usuarios.edit', $user))
            ->assertOk()
            ->assertDontSee($hash);

        $this->actingAs($administrator)
            ->patch(route('admin.usuarios.update', $user), [
                'nombres' => '  María   José ',
                'apellidos' => '  Quispe  Mamani ',
                'email' => $user->email,
                'telefono' => '71234567',
                'rol' => 'administrador',
                'estado' => false,
            ])
            ->assertRedirect(route('admin.usuarios.show', $user));

        $user->refresh();
        $this->assertSame('María José', $user->nombres);
        $this->assertSame('Quispe Mamani', $user->apellidos);
        $this->assertSame('original@example.com', $user->email);
        $this->assertSame('71234567', $user->telefono);
        $this->assertSame('ciudadano', $user->rol);
        $this->assertTrue($user->estado);
        $this->assertTrue(Hash::check('secreto-no-visible', $user->password));
    }

    public function test_user_edit_rejects_invalid_names_email_duplicates_and_invalid_phone(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $user = User::factory()->create(['email' => 'editar@example.com']);
        $duplicate = User::factory()->create(['email' => 'duplicado@example.com']);

        $this->actingAs($administrator)
            ->patch(route('admin.usuarios.update', $user), [
                'nombres' => 'Ana2',
                'apellidos' => '   ',
                'email' => $duplicate->email,
                'telefono' => '12345',
            ])
            ->assertSessionHasErrors(['nombres', 'apellidos', 'email', 'telefono']);
    }

    public function test_non_administrators_cannot_view_or_edit_users(): void
    {
        $target = User::factory()->create();

        foreach (['ciudadano', 'voluntario'] as $role) {
            $actor = User::factory()->create(['rol' => $role]);
            $this->actingAs($actor)->get(route('admin.usuarios.show', $target))->assertForbidden();
            $this->actingAs($actor)->get(route('admin.usuarios.edit', $target))->assertForbidden();
            $this->actingAs($actor)->patch(route('admin.usuarios.update', $target), $this->userData())->assertForbidden();
        }
    }

    public function test_user_filters_are_preserved_in_pagination_and_can_be_cleared(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        User::factory()->count(16)->create(['rol' => 'ciudadano', 'estado' => true, 'nombres' => 'Laura']);

        $this->actingAs($administrator)
            ->get('/admin/usuarios?buscar=Laura&rol=ciudadano&estado=activo&page=2')
            ->assertOk()
            ->assertSee('buscar=Laura')
            ->assertSee('rol=ciudadano')
            ->assertSee('estado=activo');

        $this->actingAs($administrator)
            ->get(route('admin.usuarios.index'))
            ->assertOk()
            ->assertSee('LIMPIAR');
    }

    public function test_only_administrator_sees_users_navigation_link(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $citizen = User::factory()->create(['rol' => 'ciudadano']);
        $volunteer = User::factory()->create(['rol' => 'voluntario']);

        $this->actingAs($administrator)->get('/admin/panel')->assertSee('Usuarios');
        $this->actingAs($citizen)->get('/profile')->assertDontSee('Usuarios');
        $this->actingAs($volunteer)->get('/profile')->assertDontSee('Usuarios');
    }

    private function userData(): array
    {
        return [
            'nombres' => 'María',
            'apellidos' => 'Quispe',
            'email' => 'usuario@example.com',
            'telefono' => '71234567',
        ];
    }
}
