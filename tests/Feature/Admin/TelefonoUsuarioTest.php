<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Rules\TelefonoBoliviano;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El panel de administración usa la misma regla de teléfono que el registro público.
 */
class TelefonoUsuarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuarios_existentes_con_prefijo_5_6_o_7_se_pueden_editar_sin_cambiar_el_telefono(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);

        foreach (['57123456', '61234567', '71234567', null] as $telefono) {
            $usuario = User::factory()->create(['rol' => 'ciudadano', 'telefono' => $telefono]);

            $this->actingAs($admin)
                ->patch(route('admin.usuarios.update', $usuario), [
                    'nombres' => 'Nombre Editado',
                    'apellidos' => $usuario->apellidos,
                    'email' => $usuario->email,
                    'telefono' => $telefono,
                ])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('admin.usuarios.show', $usuario));

            $usuario->refresh();
            $this->assertSame('Nombre Editado', $usuario->nombres);
            $this->assertSame($telefono, $usuario->telefono);
        }
    }

    public function test_el_panel_normaliza_el_telefono_a_ocho_digitos(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $usuario = User::factory()->create(['rol' => 'ciudadano']);

        $this->actingAs($admin)
            ->patch(route('admin.usuarios.update', $usuario), [
                'nombres' => $usuario->nombres,
                'apellidos' => $usuario->apellidos,
                'email' => $usuario->email,
                'telefono' => '+591 6123-4567',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('61234567', $usuario->refresh()->telefono);
    }

    public function test_el_panel_rechaza_telefonos_invalidos_con_el_mismo_mensaje(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $usuario = User::factory()->create(['rol' => 'ciudadano', 'telefono' => '71234567']);

        foreach (['44123456', '81234567', '12345', '7123abcd'] as $telefono) {
            $this->actingAs($admin)
                ->patch(route('admin.usuarios.update', $usuario), [
                    'nombres' => $usuario->nombres,
                    'apellidos' => $usuario->apellidos,
                    'email' => $usuario->email,
                    'telefono' => $telefono,
                ])
                ->assertSessionHasErrors(['telefono' => TelefonoBoliviano::MENSAJE]);
        }

        $this->assertSame('71234567', $usuario->refresh()->telefono);
    }

    public function test_vaciar_el_telefono_lo_deja_en_null(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $usuario = User::factory()->create(['rol' => 'ciudadano', 'telefono' => '61234567']);

        $this->actingAs($admin)
            ->patch(route('admin.usuarios.update', $usuario), [
                'nombres' => $usuario->nombres,
                'apellidos' => $usuario->apellidos,
                'email' => $usuario->email,
                'telefono' => '  ',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($usuario->refresh()->telefono);
    }

    public function test_el_formulario_no_sugiere_nueve_digitos(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $usuario = User::factory()->create(['rol' => 'ciudadano']);

        $this->actingAs($admin)->get(route('admin.usuarios.edit', $usuario))
            ->assertOk()
            ->assertSee('placeholder="Ej. 71234567"', false)
            ->assertDontSee('7XXXXXXXX');
    }
}
