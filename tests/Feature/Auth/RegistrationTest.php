<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'nombres' => 'Usuario',
            'apellidos' => 'De Prueba',
            'email' => 'test@example.com',
            'telefono' => '70000000',
            'password' => 'ClaveSegura123',
            'password_confirmation' => 'ClaveSegura123',
            'acepta_terminos' => '1',
            'acepta_privacidad' => '1',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('usuarios', [
            'nombres' => 'Usuario',
            'apellidos' => 'De Prueba',
            'email' => 'test@example.com',
            'rol' => 'ciudadano',
        ]);
        // Sin destino pendiente, el ciudadano va al catálogo público de mascotas.
        $response->assertRedirect(route('mascotas.catalogo', absolute: false));
    }

    public function test_registration_cannot_assign_an_administrative_role(): void
    {
        $this->post('/register', [
            'nombres' => 'Usuario',
            'apellidos' => 'Ciudadano',
            'email' => 'ciudadano@example.com',
            'telefono' => '70000000',
            'password' => 'ClaveSegura123',
            'password_confirmation' => 'ClaveSegura123',
            'acepta_terminos' => '1',
            'acepta_privacidad' => '1',
            'rol' => 'administrador',
        ]);

        $this->assertDatabaseHas('usuarios', [
            'email' => 'ciudadano@example.com',
            'rol' => 'ciudadano',
        ]);
    }

    public function test_registration_preserves_an_intended_protected_destination(): void
    {
        $intended = route('postulaciones-voluntariado.create');

        $this->get($intended)->assertRedirect(route('login'));
        $this->get(route('register'))->assertOk();

        $this->post(route('register'), [
            'nombres' => 'Nueva',
            'apellidos' => 'Ciudadana',
            'email' => 'nueva@example.com',
            'telefono' => '70000000',
            'password' => 'ClaveSegura123',
            'password_confirmation' => 'ClaveSegura123',
            'acepta_terminos' => '1',
            'acepta_privacidad' => '1',
        ])->assertRedirect($intended);
    }

    public function test_los_nombres_se_guardan_con_mayuscula_inicial(): void
    {
        $this->post(route('register'), [
            'nombres' => 'jose  carlos',
            'apellidos' => 'COPA de la cruz',
            'email' => 'jose.copa@example.com',
            'telefono' => '70000001',
            'password' => 'ClaveSegura123',
            'password_confirmation' => 'ClaveSegura123',
            'acepta_terminos' => '1',
            'acepta_privacidad' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('usuarios', ['email' => 'jose.copa@example.com', 'nombres' => 'Jose Carlos', 'apellidos' => 'Copa de la Cruz']);

        // También cuando se guarda por otro camino (panel del administrador, modelo directo).
        $usuario = \App\Models\User::factory()->create(['nombres' => 'daniela', 'apellidos' => 'flores mendoza']);
        $this->assertSame(['Daniela', 'Flores Mendoza'], [$usuario->fresh()->nombres, $usuario->fresh()->apellidos]);
    }
}
