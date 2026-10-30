<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Rules\TelefonoBoliviano;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

/**
 * Validación, normalización y seguridad del registro público (Etapa 4).
 */
class RegistroValidacionTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'ClaveSegura123';

    public function test_registro_valido_crea_un_ciudadano_activo_y_autentica(): void
    {
        $this->post('/register', $this->datos())->assertRedirect();

        $usuario = User::where('email', 'ana.quispe@example.test')->sole();
        $this->assertAuthenticatedAs($usuario);
        $this->assertSame('ciudadano', $usuario->rol);
        $this->assertTrue($usuario->estado);
        $this->assertNotSame(self::CLAVE, $usuario->password);
    }

    public function test_el_registro_regenera_la_sesion(): void
    {
        $this->get('/register');
        $idAnterior = session()->getId();

        $this->post('/register', $this->datos())->assertRedirect();

        $this->assertNotSame($idAnterior, session()->getId());
    }

    public function test_normaliza_correo_y_nombres(): void
    {
        $this->post('/register', $this->datos([
            'nombres' => '  María   José ',
            'apellidos' => ' Quispe    Mamani  ',
            'email' => '  Ana.Quispe@Example.TEST ',
        ]))->assertRedirect();

        $this->assertDatabaseHas('usuarios', [
            'nombres' => 'María José',
            'apellidos' => 'Quispe Mamani',
            'email' => 'ana.quispe@example.test',
        ]);
    }

    public function test_correo_duplicado_muestra_mensaje_especifico_y_enlaces(): void
    {
        User::factory()->create(['email' => 'existente@example.test']);

        $this->from('/register')->post('/register', $this->datos(['email' => ' EXISTENTE@example.test ']))
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['email' => 'Este correo ya está registrado.']);

        $html = $this->get('/register')->getContent();
        $this->assertStringContainsString('Este correo ya está registrado.', $html);
        $this->assertStringContainsString('href="'.route('login').'"', $html);
        $this->assertStringContainsString('href="'.route('password.request').'"', $html);
        $this->assertStringContainsString('recupera tu contraseña', $html);
        $this->assertSame(1, User::where('email', 'existente@example.test')->count());
    }

    public function test_contrasenas_distintas_se_rechazan(): void
    {
        $this->post('/register', $this->datos(['password_confirmation' => 'OtraClave456']))
            ->assertSessionHasErrors(['password' => 'Las contraseñas no coinciden. Escribe la misma contraseña en los dos campos.']);
        $this->assertGuest();
    }

    public function test_campos_obligatorios_con_mensajes_en_espanol(): void
    {
        $this->post('/register', [])->assertSessionHasErrors([
            'nombres' => 'Escribe tus nombres.',
            'apellidos' => 'Escribe tus apellidos.',
            'email' => 'Escribe tu correo electrónico.',
            'password' => 'Escribe una contraseña.',
        ]);
    }

    public function test_mensajes_de_formato_y_longitud_en_espanol_natural(): void
    {
        $this->post('/register', $this->datos([
            'nombres' => str_repeat('a', 101),
            'apellidos' => str_repeat('b', 101),
            'email' => 'no-es-correo',
        ]))->assertSessionHasErrors([
            'nombres' => 'Los nombres pueden tener como máximo 100 caracteres.',
            'apellidos' => 'Los apellidos pueden tener como máximo 100 caracteres.',
            'email' => 'Escribe un correo electrónico válido, por ejemplo nombre@correo.com.',
        ]);
        $this->app['cache']->store()->flush();

        $this->post('/register', $this->datos(['email' => str_repeat('a', 250).'@example.test']))
            ->assertSessionHasErrors(['email' => 'El correo electrónico puede tener como máximo 255 caracteres.']);
    }

    public function test_mensajes_de_requisitos_de_contrasena_en_espanol_natural(): void
    {
        $this->post('/register', $this->datos(['password' => 'abc', 'password_confirmation' => 'abc']));

        $mensajes = session('errors')->get('password');
        $this->assertContains('La contraseña debe tener al menos 8 caracteres.', $mensajes);
        $this->assertContains('La contraseña debe incluir al menos una letra mayúscula y una minúscula.', $mensajes);
        $this->assertContains('La contraseña debe incluir al menos un número.', $mensajes);
        foreach ($mensajes as $mensaje) {
            $this->assertStringNotContainsString('El campo', $mensaje);
        }
    }

    public function test_mensaje_de_contrasena_filtrada_en_espanol_natural(): void
    {
        // En producción Password::defaults() añade uncompromised(); aquí se simula con un verificador falso.
        Password::defaults(fn () => Password::min(8)->uncompromised());
        $this->app->instance(UncompromisedVerifier::class, new class implements UncompromisedVerifier
        {
            public function verify($data)
            {
                return false;
            }
        });

        $this->post('/register', $this->datos())
            ->assertSessionHasErrors(['password' => 'Esta contraseña apareció en una filtración de datos. Elige otra.']);
    }

    public function test_los_mensajes_globales_de_otras_pantallas_no_cambian(): void
    {
        // lang/es no se modificó: el resto del sistema mantiene sus mensajes.
        $this->assertSame('El campo :attribute es obligatorio.', __('validation.required'));
        $this->assertSame('El campo correo electrónico es obligatorio.', __('validation.required', ['attribute' => __('validation.attributes.email')]));
        $this->assertSame('El correo o la contraseña no son correctos.', __('auth.failed'));
    }

    public function test_correo_invalido_se_rechaza(): void
    {
        foreach (['no-es-correo', 'ana@', '@example.test', str_repeat('a', 250).'@example.test'] as $correo) {
            $this->post('/register', $this->datos(['email' => $correo]))->assertSessionHasErrors('email');
            $this->app['cache']->store()->flush(); // límite de 5 registros por minuto
        }
        $this->assertSame(0, User::count());
    }

    public function test_telefono_vacio_se_guarda_como_null(): void
    {
        $this->post('/register', $this->datos(['telefono' => '   ']))->assertRedirect();

        $this->assertNull(User::where('email', 'ana.quispe@example.test')->sole()->telefono);
    }

    public function test_acepta_moviles_con_prefijo_5_6_y_7_y_guarda_ocho_digitos(): void
    {
        foreach ([
            '57123456' => '57123456',
            '61234567' => '61234567',
            '71234567' => '71234567',
            '+591 7123-4567' => '71234567',
            '591 6123 4567' => '61234567',
        ] as $entrada => $guardado) {
            // Un correo distinto por caso: varias entradas se guardan con el mismo número.
            $correo = 'tel'.User::count().'@example.test';
            // (string): PHP convierte en enteros las claves numéricas; un formulario siempre envía texto.
            $this->post('/register', $this->datos(['email' => $correo, 'telefono' => (string) $entrada]))->assertSessionHasNoErrors();
            $this->assertSame($guardado, User::where('email', $correo)->sole()->telefono);
            $this->post('/logout');
            $this->app['cache']->store()->flush();
        }
    }

    public function test_rechaza_telefonos_invalidos_con_mensaje_claro(): void
    {
        foreach (['44123456', '81234567', '7123456', '712345678', '12345', '7123abcd', str_repeat('7', 30)] as $telefono) {
            $this->post('/register', $this->datos(['telefono' => $telefono]))
                ->assertSessionHasErrors(['telefono' => TelefonoBoliviano::MENSAJE]);
            $this->assertCount(1, session('errors')->get('telefono'));
            $this->app['cache']->store()->flush();
        }
        $this->assertSame(0, User::count());
    }

    public function test_acepta_nombres_con_tildes_enie_apostrofes_guiones_y_puntos(): void
    {
        foreach ([
            ['Ñusta María-José', "O'Connor Peña"],
            ['Ma. Fernanda', 'D’Angelo Quispe'],
            ['Ángel Úrsula', 'Mamani-Choque'],
        ] as $indice => [$nombres, $apellidos]) {
            $correo = "nombre{$indice}@example.test";
            $this->post('/register', $this->datos(compact('nombres', 'apellidos') + ['email' => $correo]))->assertSessionHasNoErrors();
            $this->assertDatabaseHas('usuarios', ['email' => $correo, 'nombres' => $nombres, 'apellidos' => $apellidos]);
            $this->post('/logout');
            $this->app['cache']->store()->flush();
        }
    }

    public function test_rechaza_nombres_con_numeros_o_simbolos(): void
    {
        // Un carácter nulo en medio (en los extremos lo elimina el TrimStrings global de Laravel).
        foreach (['Ana2', '<script>', '-Ana', "An\u{0000}a", 'Ana@', 'Ana_María'] as $nombre) {
            $this->post('/register', $this->datos(['nombres' => $nombre]))
                // Mensaje en español natural (no "El campo nombres...")
                ->assertSessionHasErrors(['nombres' => 'Los nombres solo pueden contener letras, espacios, apóstrofes, guiones y puntos.']);
            $this->app['cache']->store()->flush();
        }
        $this->assertSame(0, User::count());
    }

    public function test_rol_y_estado_enviados_no_elevan_privilegios(): void
    {
        $this->post('/register', $this->datos([
            'rol' => 'administrador',
            'estado' => false,
            'email_verified_at' => '2020-01-01',
            'id' => 9999,
        ]))->assertRedirect();

        $usuario = User::where('email', 'ana.quispe@example.test')->sole();
        $this->assertSame('ciudadano', $usuario->rol);
        $this->assertTrue($usuario->estado);
        $this->assertNotSame(9999, $usuario->id);
    }

    public function test_el_honeypot_completado_rechaza_el_registro(): void
    {
        $this->from('/register')->post('/register', $this->datos(['sitio_web' => 'https://spam.example']))
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['sitio_web' => 'No pudimos procesar tu registro. Inténtalo de nuevo.']);

        $this->assertSame(0, User::count());
        $this->assertGuest();
        $html = $this->get('/register')->getContent();
        $this->assertStringContainsString('No pudimos procesar tu registro. Inténtalo de nuevo.', $html);
        $this->assertStringNotContainsString('spam.example', $html);
    }

    public function test_el_honeypot_vacio_no_afecta_al_registro(): void
    {
        $this->post('/register', $this->datos(['sitio_web' => '']))->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    }

    public function test_el_honeypot_esta_oculto_y_fuera_del_teclado(): void
    {
        $html = $this->get('/register')->getContent();

        $this->assertMatchesRegularExpression('/<div class="absolute -left-\[10000px\][^"]*" aria-hidden="true">\s*<label for="sitio_web">/', $html);
        $this->assertMatchesRegularExpression('/<input id="sitio_web" name="sitio_web" type="text" value="" tabindex="-1" autocomplete="off">/', $html);
        $this->assertStringNotContainsString('style=', $html);
    }

    public function test_tras_un_error_se_conservan_los_datos_no_sensibles_y_no_las_contrasenas(): void
    {
        $this->from('/register')->post('/register', $this->datos([
            'email' => 'no-es-correo',
            'password' => 'Secreta-que-no-vuelve-1',
            'password_confirmation' => 'Secreta-que-no-vuelve-1',
            'acepta_terminos' => '1',
            'acepta_privacidad' => '1',
        ]));

        $html = $this->get('/register')->getContent();
        $this->assertStringContainsString('value="Ana"', $html);
        $this->assertStringContainsString('value="Quispe"', $html);
        $this->assertStringContainsString('value="no-es-correo"', $html);
        $this->assertStringContainsString('value="71234567"', $html);
        $this->assertStringNotContainsString('Secreta-que-no-vuelve-1', $html);
    }

    public function test_sin_destino_pendiente_redirige_al_catalogo_publico(): void
    {
        $this->post('/register', $this->datos())
            ->assertRedirect(route('mascotas.catalogo', absolute: false));

        // El ciudadano recién creado puede ver el catálogo. Petición nueva: el usuario se vuelve a leer
        // de la base (rol y estado los asigna PostgreSQL), como en un navegador.
        app('auth')->forgetGuards();
        $this->get(route('mascotas.catalogo'))->assertOk();
    }

    public function test_respeta_el_destino_protegido_solicitado_antes_de_registrarse(): void
    {
        $destino = route('solicitudes-adopcion.index');

        $this->get($destino)->assertRedirect(route('login'));
        $this->get(route('register'))->assertOk();

        $this->post(route('register'), $this->datos())->assertRedirect($destino);
        // Petición nueva: el usuario se vuelve a leer de la base, como en un navegador.
        app('auth')->forgetGuards();
        $this->get($destino)->assertOk();
    }

    public function test_visitar_paginas_publicas_no_cambia_el_destino_predeterminado(): void
    {
        $this->get(route('home'))->assertOk();
        $this->get(route('mascotas.catalogo'))->assertOk();
        $this->get(route('register'))->assertOk();

        $this->post('/register', $this->datos())
            ->assertRedirect(route('mascotas.catalogo', absolute: false));
    }

    public function test_el_registro_fallido_no_consume_el_destino_pendiente(): void
    {
        $destino = route('postulaciones-voluntariado.create');
        $this->get($destino)->assertRedirect(route('login'));

        $this->post('/register', $this->datos(['email' => 'no-es-correo']))->assertSessionHasErrors('email');
        $this->post('/register', $this->datos())->assertRedirect($destino);
    }

    public function test_un_usuario_autenticado_no_puede_volver_al_registro(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/register')->assertRedirect();
        $this->post('/register', $this->datos())->assertRedirect();
        $this->assertSame(1, User::count());
    }

    private function datos(array $cambios = []): array
    {
        return array_replace([
            'nombres' => 'Ana',
            'apellidos' => 'Quispe',
            'email' => 'ana.quispe@example.test',
            'telefono' => '71234567',
            'password' => self::CLAVE,
            'password_confirmation' => self::CLAVE,
            'acepta_terminos' => '1',
            'acepta_privacidad' => '1',
        ], $cambios);
    }
}
