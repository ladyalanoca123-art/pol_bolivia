<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Registro de seguridad del honeypot y página 429 genérica (Etapa 5).
 */
class RegistroSeguridadTest extends TestCase
{
    use RefreshDatabase;

    private const MENSAJE_429 = 'Has realizado demasiadas solicitudes. Espera un momento antes de volver a intentarlo.';

    /** @var array<int, array{mensaje: string, contexto: array}> */
    private array $registros = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Captura lo que se escribe en el canal "seguridad" sin escribir archivos.
        Event::listen(MessageLogged::class, function (MessageLogged $evento): void {
            if (str_starts_with($evento->message, 'seguridad.')) {
                $this->registros[] = ['mensaje' => $evento->message, 'contexto' => $evento->context];
            }
        });
    }

    public function test_el_honeypot_completado_se_registra_sin_datos_del_formulario(): void
    {
        $this->withHeader('User-Agent', str_repeat('Bot/1.0 ', 60))
            ->post('/register', $this->datos(['sitio_web' => 'https://spam-secreto.example']))
            ->assertSessionHasErrors('sitio_web');

        $this->assertSame(['seguridad.registro_honeypot'], array_column($this->registros, 'mensaje'));

        $contexto = $this->registros[0]['contexto'];
        $this->assertSame(['evento', 'ip', 'user_agent', 'fecha'], array_keys($contexto));
        $this->assertSame('registro_honeypot', $contexto['evento']);
        $this->assertSame(255, mb_strlen($contexto['user_agent']));

        // Ni lo escrito en el honeypot ni ningún otro dato del formulario.
        $todo = json_encode($this->registros);
        foreach (['spam-secreto', 'ClaveSegura123', 'bot.honeypot', 'Quispe', '71234567', 'password', 'sitio_web'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $todo);
        }
        $this->assertSame(0, User::count());
    }

    public function test_el_honeypot_se_registra_aunque_haya_otros_errores(): void
    {
        $this->post('/register', ['sitio_web' => 'x'])->assertSessionHasErrors(['sitio_web', 'nombres', 'email']);

        $this->assertSame(['seguridad.registro_honeypot'], array_column($this->registros, 'mensaje'));
    }

    public function test_sin_honeypot_no_se_registra_el_evento(): void
    {
        // Con errores normales de validación.
        $this->post('/register', $this->datos(['email' => 'no-es-correo']))->assertSessionHasErrors('email');
        // Y con un registro correcto (que sí genera el evento "login").
        $this->post('/register', $this->datos(['sitio_web' => '']))->assertSessionHasNoErrors();

        $this->assertNotContains('seguridad.registro_honeypot', array_column($this->registros, 'mensaje'));
        $this->assertContains('seguridad.login', array_column($this->registros, 'mensaje'));
    }

    public function test_si_el_log_no_se_puede_escribir_el_honeypot_no_provoca_error_500(): void
    {
        // Un directorio como destino: el archivo no se puede abrir.
        // El log principal va al canal nulo para no escribir el aviso en storage/logs/laravel.log.
        config([
            'logging.channels.seguridad' => ['driver' => 'single', 'path' => storage_path('logs')],
            'logging.default' => 'null',
        ]);

        $this->from('/register')->post('/register', $this->datos(['sitio_web' => 'x']))
            ->assertStatus(302)
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['sitio_web' => 'No pudimos procesar tu registro. Inténtalo de nuevo.']);
        $this->assertSame(0, User::count());
    }

    public function test_el_limite_de_registro_muestra_la_pagina_429_generica(): void
    {
        foreach (range(1, 5) as $intento) {
            $this->post('/register', ['email' => "bot{$intento}@example.test"])->assertStatus(302);
        }

        $respuesta = $this->post('/register', ['email' => 'bot6@example.test'])->assertStatus(429);

        $respuesta->assertSee('Demasiadas solicitudes')
            ->assertSee(self::MENSAJE_429)
            ->assertDontSee('minuto')
            ->assertDontSee('hora');
    }

    public function test_la_pagina_429_generica_tambien_se_usa_en_otros_limites(): void
    {
        foreach (range(1, 60) as $intento) {
            $this->get('/mascotas')->assertOk();
        }

        $this->get('/mascotas')->assertStatus(429)->assertSee(self::MENSAJE_429);
    }

    private function datos(array $cambios = []): array
    {
        return array_replace([
            'nombres' => 'Ana',
            'apellidos' => 'Quispe',
            'email' => 'bot.honeypot@example.test',
            'telefono' => '71234567',
            'password' => 'ClaveSegura123',
            'password_confirmation' => 'ClaveSegura123',
            'acepta_terminos' => '1',
            'acepta_privacidad' => '1',
        ], $cambios);
    }
}
