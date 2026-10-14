<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Seguridad y accesibilidad del inicio de sesión y de la recuperación de contraseña.
 */
class InicioSesionSeguridadTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'Clave-Prueba-123';

    private const ERROR_CREDENCIALES = 'El correo o la contraseña no son correctos.';

    private const ERROR_DESACTIVADA = 'Tu cuenta está desactivada. Comunícate con la fundación.';

    private const RESPUESTA_RECUPERAR = 'Si el correo está registrado, te enviaremos un enlace para restablecer tu contraseña.';

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

    // 1. Inicio de sesión correcto y regeneración de la sesión.
    public function test_inicio_de_sesion_correcto_regenera_la_sesion(): void
    {
        $usuario = $this->usuario();
        $this->get('/login');
        $idAnterior = session()->getId();

        $this->post('/login', ['email' => $usuario->email, 'password' => self::CLAVE])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($usuario);
        $this->assertNotSame($idAnterior, session()->getId());
    }

    // 2. Credenciales incorrectas: el mismo mensaje si el correo existe o no.
    public function test_credenciales_incorrectas_no_revelan_si_el_correo_existe(): void
    {
        $usuario = $this->usuario();

        $this->post('/login', ['email' => $usuario->email, 'password' => 'otra-clave'])
            ->assertSessionHasErrors(['email' => self::ERROR_CREDENCIALES]);
        $this->post('/login', ['email' => 'nadie@example.test', 'password' => 'otra-clave'])
            ->assertSessionHasErrors(['email' => self::ERROR_CREDENCIALES]);

        $this->assertGuest();
    }

    // 3. Cuenta desactivada: bloqueada solo con credenciales correctas, sin dejar sesión.
    public function test_cuenta_desactivada_no_puede_iniciar_sesion(): void
    {
        $usuario = $this->usuario(['estado' => false]);

        $this->post('/login', ['email' => $usuario->email, 'password' => self::CLAVE])
            ->assertSessionHasErrors(['email' => self::ERROR_DESACTIVADA]);

        $this->assertGuest();
        $this->get('/profile')->assertRedirect(route('login'));
    }

    public function test_con_contrasena_incorrecta_no_se_revela_que_la_cuenta_esta_desactivada(): void
    {
        $usuario = $this->usuario(['estado' => false]);

        $respuesta = $this->post('/login', ['email' => $usuario->email, 'password' => 'otra-clave']);

        $respuesta->assertSessionHasErrors(['email' => self::ERROR_CREDENCIALES]);
        $this->assertStringNotContainsString('desactivada', json_encode(session('errors')->all()));
        $this->assertGuest();
    }

    // 4. Límite de 5 intentos por correo e IP (LoginRequest).
    public function test_bloqueo_tras_cinco_intentos_fallidos_por_correo_e_ip(): void
    {
        Event::fake([Lockout::class]);
        $usuario = $this->usuario();

        foreach (range(1, 5) as $intento) {
            $this->post('/login', ['email' => $usuario->email, 'password' => 'otra-clave'])
                ->assertSessionHasErrors(['email' => self::ERROR_CREDENCIALES]);
        }

        // El sexto intento queda bloqueado aunque la contraseña sea correcta.
        $this->post('/login', ['email' => $usuario->email, 'password' => self::CLAVE])
            ->assertSessionHasErrors('email');
        $this->assertStringContainsString('Demasiados intentos', session('errors')->first('email'));
        $this->assertGuest();
        Event::assertDispatched(Lockout::class);

        // Pasado el tiempo de bloqueo (sin esperas reales) vuelve a permitir el acceso.
        $this->travel(61)->seconds();
        $this->post('/login', ['email' => $usuario->email, 'password' => self::CLAVE])->assertRedirect();
        $this->assertAuthenticatedAs($usuario);
    }

    // 5. Límite independiente de 20 peticiones por minuto por IP (throttle:login).
    public function test_limite_de_veinte_peticiones_por_minuto_por_ip_responde_429(): void
    {
        // Correos distintos para que no actúe el límite de 5 intentos por correo.
        foreach (range(1, 20) as $intento) {
            $this->post('/login', ['email' => "intento{$intento}@example.test", 'password' => 'x'])->assertStatus(302);
        }

        $this->post('/login', ['email' => 'intento21@example.test', 'password' => 'x'])
            ->assertStatus(429)
            ->assertSee('Demasiadas solicitudes')
            ->assertSee('Has realizado demasiadas solicitudes. Espera un momento antes de volver a intentarlo.');

        // Otra IP no queda afectada.
        $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40'])
            ->post('/login', ['email' => 'otra-ip@example.test', 'password' => 'x'])
            ->assertStatus(302);

        // Al minuto siguiente la IP original vuelve a tener acceso.
        $this->travel(61)->seconds();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->post('/login', ['email' => 'intento22@example.test', 'password' => 'x'])
            ->assertStatus(302);
    }

    public function test_los_dos_limites_funcionan_a_la_vez_sin_anularse(): void
    {
        $bloqueado = $this->usuario();
        $otro = $this->usuario();

        // 5 fallos bloquean solo esa combinación de correo e IP (respuesta 302 con mensaje, no 429).
        foreach (range(1, 5) as $intento) {
            $this->post('/login', ['email' => $bloqueado->email, 'password' => 'otra-clave'])->assertStatus(302);
        }
        $this->post('/login', ['email' => $bloqueado->email, 'password' => self::CLAVE])->assertStatus(302);
        $this->assertStringContainsString('Demasiados intentos', session('errors')->first('email'));
        $this->assertGuest();

        // Otro correo desde la misma IP sigue pudiendo entrar (7.ª petición de esta IP).
        $this->post('/login', ['email' => $otro->email, 'password' => self::CLAVE])->assertRedirect();
        $this->assertAuthenticatedAs($otro);
        $this->post('/logout');

        // Peticiones 8 a 20 de esta IP; la 21.ª recibe 429 aunque las credenciales sean correctas.
        foreach (range(8, 20) as $intento) {
            $this->post('/login', ['email' => "relleno{$intento}@example.test", 'password' => 'x'])->assertStatus(302);
        }
        $this->post('/login', ['email' => $otro->email, 'password' => self::CLAVE])->assertStatus(429);
        $this->assertGuest();

        // El límite por IP solo afecta a POST /login: la pantalla de acceso sigue disponible.
        $this->get('/login')->assertOk();
    }

    public function test_si_el_log_de_seguridad_no_se_puede_escribir_el_acceso_sigue_funcionando(): void
    {
        // Un directorio como destino: el archivo no se puede abrir.
        // El log principal va al canal nulo para no escribir el aviso en storage/logs/laravel.log.
        config([
            'logging.channels.seguridad' => ['driver' => 'single', 'path' => storage_path('logs')],
            'logging.default' => 'null',
        ]);
        $usuario = $this->usuario();

        $this->post('/login', ['email' => $usuario->email, 'password' => 'otra-clave'])
            ->assertStatus(302)->assertSessionHasErrors(['email' => self::ERROR_CREDENCIALES]);
        $this->post('/login', ['email' => $usuario->email, 'password' => self::CLAVE])->assertRedirect();
        $this->assertAuthenticatedAs($usuario);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_recordarme_sigue_funcionando(): void
    {
        $usuario = $this->usuario();

        $respuesta = $this->post('/login', ['email' => $usuario->email, 'password' => self::CLAVE, 'remember' => 'on']);

        $respuesta->assertCookie(app('auth')->guard()->getRecallerName());
        $this->assertNotNull($usuario->refresh()->remember_token);
    }

    // 6. Recuperación de contraseña sin enumeración de cuentas.
    public function test_recuperar_contrasena_responde_igual_exista_o_no_el_correo(): void
    {
        Notification::fake();
        $usuario = $this->usuario();

        $registrado = $this->from('/forgot-password')->post('/forgot-password', ['email' => $usuario->email]);
        $estadoRegistrado = session('status');
        $erroresRegistrado = session('errors')?->all() ?? [];

        $inexistente = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'nadie@example.test']);
        $estadoInexistente = session('status');
        $erroresInexistente = session('errors')?->all() ?? [];

        $registrado->assertRedirect('/forgot-password');
        $inexistente->assertRedirect('/forgot-password');
        $this->assertSame(self::RESPUESTA_RECUPERAR, $estadoRegistrado);
        $this->assertSame($estadoRegistrado, $estadoInexistente);
        $this->assertSame([], $erroresRegistrado);
        $this->assertSame([], $erroresInexistente);

        // El enlace sí se envía a la cuenta existente.
        Notification::assertSentTo($usuario, ResetPassword::class);
        Notification::assertSentTimes(ResetPassword::class, 1);

        $this->get('/forgot-password')->assertSee(self::RESPUESTA_RECUPERAR);
    }

    public function test_un_fallo_del_correo_no_cambia_la_respuesta_visible(): void
    {
        $usuario = $this->usuario();
        // El servidor de correo falla al enviar (solo ocurre cuando la cuenta existe).
        // El log principal va al canal nulo para no escribir el error en storage/logs/laravel.log.
        config([
            'mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1,
            'logging.default' => 'null',
        ]);

        $this->from('/forgot-password')->post('/forgot-password', ['email' => $usuario->email])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('status', self::RESPUESTA_RECUPERAR)
            ->assertSessionHasNoErrors();
    }

    public function test_el_enlace_de_recuperacion_expira_a_los_30_minutos(): void
    {
        $this->assertSame(30, config('auth.passwords.users.expire'));
    }

    // 7. Normalización del correo, entradas inválidas y límites de longitud.
    public function test_el_correo_se_normaliza_antes_de_validar(): void
    {
        $usuario = $this->usuario(['email' => 'ana.perez@example.test']);

        $this->post('/login', ['email' => "  ANA.Perez@Example.TEST \t", 'password' => self::CLAVE])->assertRedirect();

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_entradas_invalidas_y_demasiado_largas_se_rechazan(): void
    {
        $this->post('/login', [])->assertSessionHasErrors(['email', 'password']);
        $this->post('/login', ['email' => 'no-es-un-correo', 'password' => 'x'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => ['a@example.test'], 'password' => 'x'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => str_repeat('a', 250).'@example.test', 'password' => 'x'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'a@example.test', 'password' => str_repeat('x', 256)])->assertSessionHasErrors('password');

        // Sin reglas de complejidad al iniciar sesión: una contraseña simple solo falla por credenciales.
        $this->post('/login', ['email' => 'a@example.test', 'password' => 'abc'])
            ->assertSessionHasErrors(['email' => self::ERROR_CREDENCIALES])
            ->assertSessionDoesntHaveErrors('password');
        $this->assertGuest();
    }

    // 8. Registro de eventos de seguridad sin contraseñas.
    public function test_los_eventos_de_seguridad_se_registran_sin_contrasenas(): void
    {
        $usuario = $this->usuario();
        $agenteLargo = str_repeat('Navegador/1.0 ', 40);

        $this->withHeader('User-Agent', $agenteLargo)
            ->post('/login', ['email' => $usuario->email, 'password' => 'Clave-Equivocada-999']);
        $this->withHeader('User-Agent', $agenteLargo)
            ->post('/login', ['email' => $usuario->email, 'password' => self::CLAVE]);
        $this->post('/logout');

        foreach (range(1, 6) as $intento) {
            $this->post('/login', ['email' => 'atacante@example.test', 'password' => 'Secreta-'.$intento]);
        }

        $eventos = array_column($this->registros, 'mensaje');
        $this->assertContains('seguridad.login_fallido', $eventos);
        $this->assertContains('seguridad.login', $eventos);
        $this->assertContains('seguridad.logout', $eventos);
        $this->assertContains('seguridad.bloqueo', $eventos);

        $fallido = $this->primero('seguridad.login_fallido');
        $this->assertSame($usuario->email, $fallido['correo']);
        $this->assertSame(255, mb_strlen($fallido['user_agent']));
        $this->assertArrayHasKey('ip', $fallido);
        $this->assertArrayHasKey('fecha', $fallido);

        $this->assertSame($usuario->id, $this->primero('seguridad.login')['usuario_id']);
        $this->assertSame('atacante@example.test', $this->primero('seguridad.bloqueo')['correo']);

        // Ninguna contraseña (ni campo "password") aparece en ningún registro.
        $todo = json_encode($this->registros);
        foreach (['Clave-Equivocada-999', self::CLAVE, 'Secreta-', 'password'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $todo);
        }
    }

    // 9. Invalidación de la sesión y regeneración del token CSRF al cerrar sesión.
    public function test_cerrar_sesion_invalida_la_sesion_y_regenera_el_token(): void
    {
        $usuario = $this->usuario();
        $this->post('/login', ['email' => $usuario->email, 'password' => self::CLAVE]);
        session()->put('dato_de_prueba', 'valor');
        $tokenAnterior = session()->token();

        $this->post('/logout')->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull(session('dato_de_prueba'));
        $this->assertNotSame($tokenAnterior, session()->token());
    }

    // 10. Cambio de contraseña: este dispositivo sigue dentro y las demás sesiones se cierran.
    public function test_cambiar_la_contrasena_mantiene_este_dispositivo(): void
    {
        $usuario = $this->usuario();
        $this->post('/login', ['email' => $usuario->email, 'password' => self::CLAVE]);

        $this->from('/profile')->put('/password', [
            'current_password' => self::CLAVE,
            'password' => 'Nueva-Clave-456',
            'password_confirmation' => 'Nueva-Clave-456',
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        // Petición nueva del mismo dispositivo (el usuario se vuelve a leer de la base, como en un navegador).
        app('auth')->forgetGuards();
        $this->get('/profile')->assertOk();
        $this->assertAuthenticatedAs($usuario);
        $this->assertTrue(Hash::check('Nueva-Clave-456', $usuario->refresh()->password));
    }

    public function test_las_sesiones_abiertas_con_la_contrasena_anterior_se_cierran(): void
    {
        $usuario = $this->usuario();
        $this->post('/login', ['email' => $usuario->email, 'password' => self::CLAVE]);
        $this->get('/profile')->assertOk();

        // La contraseña cambia desde otro dispositivo.
        $usuario->forceFill(['password' => Hash::make('Otra-Clave-789')])->save();

        // Petición nueva de este dispositivo (el usuario se vuelve a leer de la base, como en un navegador).
        app('auth')->forgetGuards();
        $this->get('/profile')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_la_sesion_recordada_de_otro_dispositivo_deja_de_valer_al_cambiar_la_contrasena(): void
    {
        $usuario = $this->usuario();
        $recordador = app('auth')->guard()->getRecallerName();
        $cookie = $this->post('/login', ['email' => $usuario->email, 'password' => self::CLAVE, 'remember' => 'on'])
            ->getCookie($recordador, false)->getValue();

        // Otro dispositivo: sin sesión, solo con la cookie "Recordarme" (cifrada, tal como la envía el navegador).
        $otroDispositivo = function () use ($recordador, $cookie) {
            $this->flushSession();
            app('auth')->forgetGuards();

            return $this->withUnencryptedCookie($recordador, $cookie)->get('/profile');
        };

        $otroDispositivo()->assertOk();

        $usuario->forceFill(['password' => Hash::make('Otra-Clave-789')])->save();

        $otroDispositivo()->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // 11. Protección CSRF y 12. vistas de error.
    public function test_peticion_sin_token_csrf_muestra_la_pagina_419(): void
    {
        Route::post('/_prueba-csrf', fn () => 'aceptado')->middleware(['web', CsrfEstricto::class]);

        $this->post('/_prueba-csrf')
            ->assertStatus(419)
            ->assertSee('Tu sesión expiró por seguridad. Vuelve a intentarlo.')
            ->assertSee(route('login'), false);

        $this->get('/login');
        $this->post('/_prueba-csrf', ['_token' => session()->token()])->assertOk()->assertSee('aceptado');
    }

    public function test_el_formulario_incluye_el_token_csrf(): void
    {
        $this->get('/login')->assertSee('name="_token"', false);
    }

    public function test_la_pagina_500_no_muestra_detalles_tecnicos(): void
    {
        // El log principal va al canal nulo para no escribir la excepción de prueba en storage/logs/laravel.log.
        config(['app.debug' => false, 'logging.default' => 'null']);
        Route::get('/_prueba-500', function () {
            throw new \RuntimeException('SELECT * FROM usuarios WHERE detalle_secreto');
        })->middleware('web');

        $this->get('/_prueba-500')
            ->assertStatus(500)
            ->assertSee('Algo salió mal')
            ->assertDontSee('detalle_secreto')
            ->assertDontSee('SELECT')
            ->assertDontSee(base_path(), false);
    }

    // 13. Accesibilidad básica y funcionamiento del formulario.
    public function test_el_formulario_es_accesible_y_conserva_sus_funciones(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        foreach ([
            'images/pol-bolivia/logos/POL_02_horizontal_web.png', 'alt="POL-Bolivia, Hacedores de Sueños"',
            'BIENVENIDO DE NUEVO', 'type="email"', 'autocomplete="username"', 'inputmode="email"',
            'autocapitalize="none"', 'spellcheck="false"', 'type="password"', 'autocomplete="current-password"',
            'aria-pressed="false"', 'aria-label="Mostrar contraseña"', 'id="aviso-mayus" aria-live="polite"',
            "getModifierState('CapsLock')", 'name="remember"', route('password.request'), route('register'),
            'enviando: false', 'aria-busy="false"', 'Ingresando…', 'bg-slate-50',
            'POL-Bolivia nunca te pedirá tu contraseña por WhatsApp, correo o llamada.',
            'from-blue-950/35 via-blue-950/55 to-blue-950/90', 'COMUNIDAD POL-BOLIVIA',
        ] as $esperado) {
            $this->assertStringContainsString($esperado, $html, "Falta: {$esperado}");
        }

        $this->assertStringNotContainsString('emerald', $html);
        $this->assertStringNotContainsString('stone-', $html);
        $this->assertStringNotContainsString('role="alert"', $html);
    }

    public function test_tras_un_error_se_marcan_los_campos_y_no_se_repuebla_la_contrasena(): void
    {
        $this->from('/login')->post('/login', ['email' => 'nadie@example.test', 'password' => 'Clave-que-no-debe-volver']);

        $html = $this->get('/login')->getContent();

        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('aria-invalid="true" aria-describedby="email-error"', $html);
        $this->assertStringContainsString('<li id="email-error">'.e(self::ERROR_CREDENCIALES).'</li>', $html);
        $this->assertStringContainsString('value="nadie@example.test"', $html);
        $this->assertStringNotContainsString('Clave-que-no-debe-volver', $html);
    }

    // 14. Sin regresiones en rutas.
    public function test_las_rutas_de_autenticacion_conservan_sus_nombres(): void
    {
        $this->assertSame(url('/login'), route('login'));
        $this->assertSame(url('/forgot-password'), route('password.request'));
        $this->assertSame(url('/register'), route('register'));

        $postLogin = Route::getRoutes()->match(request()->create('/login', 'POST'));
        $this->assertContains('throttle:login', $postLogin->gatherMiddleware());
        $this->assertContains('guest', $postLogin->gatherMiddleware());
    }

    private function usuario(array $atributos = []): User
    {
        return User::factory()->create($atributos + ['password' => Hash::make(self::CLAVE)]);
    }

    private function primero(string $mensaje): array
    {
        foreach ($this->registros as $registro) {
            if ($registro['mensaje'] === $mensaje) {
                return $registro['contexto'];
            }
        }

        $this->fail("No se registró {$mensaje}");
    }
}

/**
 * En las pruebas Laravel omite la verificación CSRF; esta variante la aplica de verdad.
 */
class CsrfEstricto extends ValidateCsrfToken
{
    protected function runningUnitTests()
    {
        return false;
    }
}
