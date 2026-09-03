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
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('usuarios', [
            'nombres' => 'Usuario',
            'apellidos' => 'De Prueba',
            'email' => 'test@example.com',
            'rol' => 'ciudadano',
        ]);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_cannot_assign_an_administrative_role(): void
    {
        $this->post('/register', [
            'nombres' => 'Usuario',
            'apellidos' => 'Ciudadano',
            'email' => 'ciudadano@example.com',
            'telefono' => '70000000',
            'password' => 'password',
            'password_confirmation' => 'password',
            'rol' => 'administrador',
        ]);

        $this->assertDatabaseHas('usuarios', [
            'email' => 'ciudadano@example.com',
            'rol' => 'ciudadano',
        ]);
    }
}
