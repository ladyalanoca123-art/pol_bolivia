<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un administrador no puede cambiar el correo de otro administrador (UserPolicy::updateEmail);
 * sí el suyo y el de ciudadanos y voluntarios, y el resto de los datos sigue editándose igual.
 */
class CorreoUsuarioTest extends TestCase
{
    use RefreshDatabase;

    private const ERROR = 'No puedes cambiar el correo de otro administrador.';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['rol' => 'administrador', 'email' => 'admin@example.com']);
    }

    public function test_cambia_el_correo_de_ciudadanos_y_voluntarios(): void
    {
        foreach (['ciudadano', 'voluntario'] as $rol) {
            $usuario = User::factory()->create(['rol' => $rol]);

            $this->actualizar($usuario, ['email' => "nuevo.{$rol}@example.com"])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('admin.usuarios.show', $usuario));

            $this->assertSame("nuevo.{$rol}@example.com", $usuario->fresh()->email);
        }
    }

    public function test_cambia_su_propio_correo(): void
    {
        $this->actualizar($this->admin, ['email' => 'mi.nuevo@example.com'])->assertSessionHasNoErrors();

        $this->assertSame('mi.nuevo@example.com', $this->admin->fresh()->email);
    }

    public function test_edita_los_demas_datos_de_otro_administrador_sin_cambiar_su_correo(): void
    {
        $otro = User::factory()->create(['rol' => 'administrador', 'email' => 'otro@example.com']);

        $this->actualizar($otro, ['nombres' => 'Rosa', 'apellidos' => 'Quispe', 'telefono' => '71234567'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.usuarios.show', $otro));

        $otro->refresh();
        $this->assertSame(['Rosa', 'Quispe', '71234567', 'otro@example.com'], [$otro->nombres, $otro->apellidos, $otro->telefono, $otro->email]);
    }

    public function test_no_cambia_el_correo_de_otro_administrador_aunque_se_envie_a_mano(): void
    {
        $otro = User::factory()->create(['rol' => 'administrador', 'email' => 'otro@example.com', 'nombres' => 'Ana']);

        // Petición manipulada: otro correo y otros datos; no se guarda nada.
        $this->actualizar($otro, ['email' => 'atacante@example.com', 'nombres' => 'Cambiado'])
            ->assertRedirect(route('admin.usuarios.edit', $otro))
            ->assertSessionHasErrors(['email' => self::ERROR]);

        // Mismo correo con otras mayúsculas: también es un cambio del valor guardado.
        $this->actualizar($otro, ['email' => 'OTRO@example.com'])->assertSessionHasErrors(['email' => self::ERROR]);

        // Sin el campo, el correo sigue siendo obligatorio.
        $this->actualizar($otro, ['email' => null])->assertSessionHasErrors('email');

        $otro->refresh();
        $this->assertSame(['otro@example.com', 'Ana'], [$otro->email, $otro->nombres]);
    }

    public function test_el_formulario_muestra_el_correo_de_otro_administrador_como_solo_lectura(): void
    {
        $otro = User::factory()->create(['rol' => 'administrador']);
        $ciudadano = User::factory()->create(['rol' => 'ciudadano']);

        $this->actingAs($this->admin)->get(route('admin.usuarios.edit', $otro))
            ->assertOk()
            ->assertSee('El correo de otro administrador no se puede cambiar desde aquí.')
            ->assertSee('aria-describedby="email-ayuda"', false)
            ->assertSee('readonly', false);

        $this->actingAs($this->admin)->get(route('admin.usuarios.edit', $ciudadano))
            ->assertOk()
            ->assertDontSee('El correo de otro administrador no se puede cambiar desde aquí.')
            ->assertDontSee('readonly', false);
    }

    public function test_la_regla_de_la_politica(): void
    {
        $otro = User::factory()->create(['rol' => 'administrador']);
        $inactivo = User::factory()->create(['rol' => 'administrador', 'estado' => false]);
        $ciudadano = User::factory()->create(['rol' => 'ciudadano']);

        $this->assertFalse($this->admin->can('updateEmail', $otro));
        $this->assertTrue($this->admin->can('updateEmail', $this->admin));
        $this->assertTrue($this->admin->can('updateEmail', $ciudadano));
        $this->assertFalse($inactivo->can('updateEmail', $ciudadano));
        $this->assertFalse($ciudadano->can('updateEmail', $ciudadano));
    }

    private function actualizar(User $usuario, array $cambios)
    {
        $datos = array_merge([
            'nombres' => $usuario->nombres,
            'apellidos' => $usuario->apellidos,
            'email' => $usuario->email,
            'telefono' => $usuario->telefono,
        ], $cambios);

        return $this->actingAs($this->admin)
            ->from(route('admin.usuarios.edit', $usuario))
            ->patch(route('admin.usuarios.update', $usuario), $datos);
    }
}
