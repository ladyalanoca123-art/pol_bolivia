<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ContrasenaCambiadaNotification;
use App\Notifications\RestablecerContrasenaNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Recuperación de contraseña: respuesta genérica (no revela si el correo existe), límites, correo en español con
 * la identidad de POL-Bolivia, política de contraseñas, cierre de sesiones, aviso de cambio y registro de
 * seguridad sin datos sensibles. Usa pol_bolivia_test (marca POL-BOLIVIA-PRUEBAS) y Notification::fake.
 */
class RecuperacionContrasenaTest extends TestCase
{
    use RefreshDatabase;

    private const ENVIADO = 'Si el correo está registrado, te enviaremos un enlace para restablecer tu contraseña. Revisa también tu carpeta de spam.';

    private const ENLACE_INVALIDO = 'El enlace no es válido o ya expiró. Solicita uno nuevo.';

    private const CLAVE_NUEVA = 'Nueva-Clave-Segura-2026';

    /** @var list<array{mensaje: string, contexto: array}> */
    private array $registros = [];

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        Event::listen(MessageLogged::class, function (MessageLogged $evento): void {
            if (str_starts_with($evento->message, 'seguridad.')) {
                $this->registros[] = ['mensaje' => $evento->message, 'contexto' => $evento->context];
            }
        });
    }

    // --- Pedir el enlace ---

    public function test_la_respuesta_es_la_misma_con_correo_existente_inexistente_o_desactivado(): void
    {
        $activo = User::factory()->create();
        $desactivado = User::factory()->create(['estado' => false]);

        $respuestas = [];
        foreach ([$activo->email, 'nadie.existe@example.test', $desactivado->email] as $correo) {
            $respuesta = $this->from('/forgot-password')->post('/forgot-password', ['email' => $correo]);
            $respuestas[] = [$respuesta->status(), $respuesta->headers->get('Location'), session('status'), session('errors')?->all() ?? []];
        }

        $this->assertSame([302, url('/forgot-password'), self::ENVIADO, []], $respuestas[0]);
        $this->assertSame($respuestas[0], $respuestas[1], 'correo inexistente: misma respuesta');
        $this->assertSame($respuestas[0], $respuestas[2], 'cuenta desactivada: misma respuesta');
        $this->get('/forgot-password')->assertSee(self::ENVIADO);
    }

    public function test_solo_se_envia_el_enlace_a_una_cuenta_existente_y_activa(): void
    {
        $activo = User::factory()->create();
        $desactivado = User::factory()->create(['estado' => false]);

        foreach ([$activo->email, 'nadie.existe@example.test', $desactivado->email] as $correo) {
            $this->post('/forgot-password', ['email' => $correo]);
        }

        Notification::assertSentTo($activo, RestablecerContrasenaNotification::class);
        Notification::assertNothingSentTo($desactivado);
        Notification::assertSentTimes(RestablecerContrasenaNotification::class, 1);
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', $desactivado->email)->count(), 'ni siquiera se crea un token');
    }

    public function test_el_limite_de_envios_de_laravel_da_la_misma_respuesta(): void
    {
        $usuario = User::factory()->create();

        // Segunda solicitud antes de 60 s (auth.passwords.users.throttle): Laravel no envía otro enlace.
        foreach ([1, 2] as $vez) {
            $this->from('/forgot-password')->post('/forgot-password', ['email' => $usuario->email])->assertSessionHas('status', self::ENVIADO);
        }

        Notification::assertSentTimes(RestablecerContrasenaNotification::class, 1);
        $this->assertSame(['enviado', 'limite'], array_column($this->contextos('seguridad.recuperacion_solicitada'), 'resultado'));
    }

    public function test_el_correo_esta_en_espanol_con_el_asunto_el_enlace_y_la_duracion_correctos(): void
    {
        config(['auth.passwords.users.expire' => 45]);
        $usuario = User::factory()->create(['nombres' => 'Juana']);

        $this->post('/forgot-password', ['email' => strtoupper($usuario->email)]);

        Notification::assertSentTo($usuario, RestablecerContrasenaNotification::class, function (RestablecerContrasenaNotification $notificacion) use ($usuario): bool {
            $correo = $notificacion->toMail($usuario);

            $this->assertSame('Restablece tu contraseña – POL-Bolivia', $correo->subject);
            $this->assertSame('Hola, Juana:', $correo->greeting);
            $this->assertSame('Restablecer contraseña', $correo->actionText);
            $this->assertSame(route('password.reset', ['token' => $notificacion->token, 'email' => $usuario->email]), $correo->actionUrl);
            $this->assertStringStartsWith(url('/reset-password/'), $correo->actionUrl);
            $this->assertContains('Este enlace dura 45 minutos y solo se puede usar una vez.', $correo->outroLines, 'la duración se lee de config/auth.php');
            $this->assertContains('Si no pediste este cambio, ignora este mensaje; tu contraseña no cambiará.', $correo->outroLines);

            $html = (string) $correo->render();
            foreach (['Hola, Juana:', 'Restablecer contraseña', 'Saludos,', 'El equipo de POL-Bolivia', 'copia y pega esta dirección', 'Todos los derechos reservados', '<title>POL-Bolivia</title>', '#172554', '#fbbf24', 'images/pol-bolivia/logos/POL_02_horizontal_web.png'] as $texto) {
                $this->assertStringContainsString($texto, $html);
            }
            foreach (['Hello', 'Regards', 'All rights reserved', 'trouble clicking', 'Laravel', 'Reset Password'] as $ingles) {
                $this->assertStringNotContainsString($ingles, $html);
            }

            return true;
        });
    }

    // --- Límites ---

    public function test_el_cuarto_pedido_del_mismo_correo_en_15_minutos_recibe_429_en_espanol(): void
    {
        foreach (range(1, 3) as $intento) {
            $this->post('/forgot-password', ['email' => 'alguien@example.test'])->assertStatus(302);
        }

        $this->post('/forgot-password', ['email' => 'alguien@example.test'])
            ->assertStatus(429)
            ->assertSee('Demasiadas solicitudes');

        // El límite es por correo: otro correo desde la misma IP sigue pudiendo.
        $this->post('/forgot-password', ['email' => 'otra.persona@example.test'])->assertStatus(302);
        $limite = array_values(array_filter($this->contextos('seguridad.recuperacion_solicitada'), fn ($c) => $c['resultado'] === 'limite'));
        $this->assertSame('al***@example.test', $limite[0]['correo']);
    }

    public function test_once_pedidos_desde_la_misma_ip_en_una_hora_reciben_429(): void
    {
        foreach (range(1, 10) as $i) {
            $this->post('/forgot-password', ['email' => "persona{$i}@example.test"])->assertStatus(302);
        }

        $this->post('/forgot-password', ['email' => 'persona11@example.test'])->assertStatus(429);
    }

    public function test_restablecer_tambien_tiene_limite(): void
    {
        foreach (range(1, 5) as $intento) {
            $this->post('/reset-password', ['token' => 'falso', 'email' => 'x@example.test', 'password' => 'Clave-Prueba-1', 'password_confirmation' => 'Clave-Prueba-1'])->assertStatus(302);
        }

        $this->post('/reset-password', ['token' => 'falso', 'email' => 'x@example.test', 'password' => 'Clave-Prueba-1', 'password_confirmation' => 'Clave-Prueba-1'])
            ->assertStatus(429)
            ->assertSee('Demasiadas solicitudes');
    }

    // --- Restablecer ---

    public function test_un_enlace_invalido_vencido_o_de_otra_cuenta_recibe_el_mensaje_generico(): void
    {
        $usuario = User::factory()->create();
        $token = $this->pedirEnlace($usuario);
        $clave = $usuario->password;

        $casos = [
            'token inválido' => ['token' => 'token-que-no-existe', 'email' => $usuario->email],
            'correo inexistente' => ['token' => $token, 'email' => 'nadie.existe@example.test'],
            'sin token' => ['token' => '', 'email' => $usuario->email],
        ];

        foreach ($casos as $caso => $datos) {
            $this->from('/reset-password/x')->post('/reset-password', [...$datos, 'password' => self::CLAVE_NUEVA, 'password_confirmation' => self::CLAVE_NUEVA])
                ->assertSessionHasErrors(['email' => self::ENLACE_INVALIDO]);
        }

        // Vencido: pasan más minutos que auth.passwords.users.expire.
        $this->travel(config('auth.passwords.users.expire') + 1)->minutes();
        $this->from('/reset-password/x')->post('/reset-password', ['token' => $token, 'email' => $usuario->email, 'password' => self::CLAVE_NUEVA, 'password_confirmation' => self::CLAVE_NUEVA])
            ->assertSessionHasErrors(['email' => self::ENLACE_INVALIDO]);

        $this->assertSame($clave, $usuario->fresh()->password, 'la contraseña no cambió');
    }

    public function test_una_cuenta_desactivada_despues_de_pedir_el_enlace_no_puede_usarlo(): void
    {
        $usuario = User::factory()->create();
        $token = $this->pedirEnlace($usuario);
        $usuario->forceFill(['estado' => false])->save();

        $this->from('/reset-password/x')->post('/reset-password', ['token' => $token, 'email' => $usuario->email, 'password' => self::CLAVE_NUEVA, 'password_confirmation' => self::CLAVE_NUEVA])
            ->assertSessionHasErrors(['email' => self::ENLACE_INVALIDO]);

        $this->assertFalse(Hash::check(self::CLAVE_NUEVA, $usuario->fresh()->password));
    }

    public function test_la_nueva_contrasena_cumple_la_politica_del_registro_con_sus_mensajes(): void
    {
        $usuario = User::factory()->create();
        $token = $this->pedirEnlace($usuario);

        $casos = [
            'Corta7!' => 'La contraseña debe tener al menos 8 caracteres.',
            'sinmayusculas123' => 'La contraseña debe incluir al menos una letra mayúscula y una minúscula.',
            'SinNumerosAqui' => 'La contraseña debe incluir al menos un número.',
        ];

        foreach ($casos as $clave => $mensaje) {
            $this->from('/reset-password/x')->post('/reset-password', ['token' => $token, 'email' => $usuario->email, 'password' => $clave, 'password_confirmation' => $clave])
                ->assertSessionHasErrors(['password' => $mensaje]);
        }

        $this->from('/reset-password/x')->post('/reset-password', ['token' => $token, 'email' => $usuario->email, 'password' => self::CLAVE_NUEVA, 'password_confirmation' => 'Otra-Distinta-2026'])
            ->assertSessionHasErrors(['password' => 'Las contraseñas no coinciden. Escribe la misma contraseña en los dos campos.']);

        $this->assertFalse(Hash::check(self::CLAVE_NUEVA, $usuario->fresh()->password));
    }

    public function test_tras_el_cambio_se_cierran_las_sesiones_se_renueva_recordarme_y_se_avisa(): void
    {
        config(['session.driver' => 'database']);
        $usuario = User::factory()->create(['remember_token' => 'token-recordarme-anterior']);
        $otro = User::factory()->create();
        $this->sesion('sesion-celular', $usuario);
        $this->sesion('sesion-computadora', $usuario);
        $this->sesion('sesion-de-otra-persona', $otro);
        $token = $this->pedirEnlace($usuario);

        $this->post('/reset-password', ['token' => $token, 'email' => $usuario->email, 'password' => self::CLAVE_NUEVA, 'password_confirmation' => self::CLAVE_NUEVA])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Tu contraseña se actualizó. Ya puedes iniciar sesión.');

        $usuario->refresh();
        $this->assertTrue(Hash::check(self::CLAVE_NUEVA, $usuario->password));
        $this->assertNotSame('token-recordarme-anterior', $usuario->remember_token);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $usuario->id)->count(), 'se cierran todas sus sesiones');
        $this->assertTrue(DB::table('sessions')->where('id', 'sesion-de-otra-persona')->exists(), 'las de otras personas no');

        Notification::assertSentTo($usuario, ContrasenaCambiadaNotification::class, function (ContrasenaCambiadaNotification $aviso) use ($usuario): bool {
            $correo = $aviso->toMail($usuario);
            $this->assertSame('Tu contraseña fue cambiada – POL-Bolivia', $correo->subject);
            $this->assertContains('Tu contraseña de POL-Bolivia fue cambiada. Si no fuiste tú, contacta a la administración.', $correo->introLines);

            return true;
        });

        // El enlace es de un solo uso.
        $this->from('/reset-password/x')->post('/reset-password', ['token' => $token, 'email' => $usuario->email, 'password' => 'Otra-Clave-Segura-9', 'password_confirmation' => 'Otra-Clave-Segura-9'])
            ->assertSessionHasErrors(['email' => self::ENLACE_INVALIDO]);
    }

    // --- Registro de seguridad ---

    public function test_el_registro_de_seguridad_no_guarda_datos_sensibles(): void
    {
        $activo = User::factory()->create();
        $desactivado = User::factory()->create(['estado' => false]);

        $this->post('/forgot-password', ['email' => 'juan.perez@gmail.com']);
        $this->post('/forgot-password', ['email' => $desactivado->email]);
        $token = $this->pedirEnlace($activo);
        $this->post('/reset-password', ['token' => $token, 'email' => $activo->email, 'password' => self::CLAVE_NUEVA, 'password_confirmation' => self::CLAVE_NUEVA]);

        $solicitudes = $this->contextos('seguridad.recuperacion_solicitada');
        $this->assertSame(['correo_inexistente', 'cuenta_desactivada', 'enviado'], array_column($solicitudes, 'resultado'));
        $this->assertSame('ju***@gmail.com', $solicitudes[0]['correo'], 'correo inexistente: enmascarado');
        $this->assertSame($desactivado->id, $solicitudes[1]['usuario_id']);
        $this->assertArrayNotHasKey('correo', $solicitudes[1], 'con cuenta: solo su ID');
        $this->assertSame($activo->id, $solicitudes[2]['usuario_id']);
        $this->assertSame([$activo->id], array_column($this->contextos('seguridad.contrasena_restablecida'), 'usuario_id'));

        $todo = json_encode($this->registros, JSON_UNESCAPED_UNICODE);
        foreach ([$token, self::CLAVE_NUEVA, 'juan.perez@gmail.com', $desactivado->email, $activo->email] as $sensible) {
            $this->assertStringNotContainsString($sensible, $todo);
        }
    }

    /** Pide el enlace para $usuario y devuelve el token que se le envió. */
    private function pedirEnlace(User $usuario): string
    {
        $this->post('/forgot-password', ['email' => $usuario->email]);
        $token = null;

        Notification::assertSentTo($usuario, RestablecerContrasenaNotification::class, function (RestablecerContrasenaNotification $notificacion) use (&$token): bool {
            $token = $notificacion->token;

            return true;
        });

        return $token;
    }

    private function contextos(string $mensaje): array
    {
        return array_column(array_filter($this->registros, fn (array $registro) => $registro['mensaje'] === $mensaje), 'contexto');
    }

    private function sesion(string $id, User $usuario): void
    {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $usuario->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'prueba', 'payload' => base64_encode('a:0:{}'), 'last_activity' => now()->getTimestamp()]);
    }
}
