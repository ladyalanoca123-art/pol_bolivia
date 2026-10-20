<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ContrasenaCambiadaNotification;
use App\Notifications\CorreoCambiadoNotification;
use App\Rules\TelefonoBoliviano;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * «Mi perfil»: mismas reglas que el registro, cambiar el correo exige la contraseña actual (aviso al correo
 * anterior y registro de seguridad), cambio de contraseña (cierra las otras sesiones y avisa), límites y
 * pantallas de cuenta con la identidad de POL-Bolivia. Usa pol_bolivia_test (marca POL-BOLIVIA-PRUEBAS).
 */
class MiPerfilTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'Clave-Actual-2026';

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

    // --- Mismas reglas que el registro ---

    public function test_el_perfil_valida_nombres_y_telefono_igual_que_el_registro(): void
    {
        $usuario = $this->usuario();

        $this->guardar($usuario, ['nombres' => 'Ana 2'])->assertSessionHasErrors(['nombres' => 'Los nombres solo pueden contener letras, espacios, apóstrofes, guiones y puntos.']);
        $this->guardar($usuario, ['apellidos' => str_repeat('a', 101)])->assertSessionHasErrors(['apellidos' => 'Los apellidos pueden tener como máximo 100 caracteres.']);
        $this->guardar($usuario, ['telefono' => '12345'])->assertSessionHasErrors(['telefono' => TelefonoBoliviano::MENSAJE]);

        // Y normaliza igual: espacios repetidos, tildes y teléfono con +591.
        $this->guardar($usuario, ['nombres' => '  María   José ', 'apellidos' => "O'Neil Pérez", 'telefono' => '+591 712-34-567'])->assertSessionHasNoErrors();
        $usuario->refresh();
        $this->assertSame('María José', $usuario->nombres);
        $this->assertSame("O'Neil Pérez", $usuario->apellidos);
        $this->assertSame('71234567', $usuario->telefono);
    }

    public function test_cambiar_solo_el_nombre_o_el_telefono_no_pide_la_contrasena(): void
    {
        $usuario = $this->usuario();
        $verificado = $usuario->email_verified_at;

        $this->guardar($usuario, ['nombres' => 'Rosa', 'telefono' => '61234567'])->assertSessionHasNoErrors()->assertSessionHas('status', 'profile-updated');

        $this->assertSame('Rosa', $usuario->fresh()->nombres);
        $this->assertEquals($verificado, $usuario->fresh()->email_verified_at);
        Notification::assertNothingSent();
        $this->assertSame([], $this->contextos('seguridad.correo_cambiado'));
    }

    public function test_cambiar_solo_mayusculas_del_correo_no_es_un_cambio_de_correo(): void
    {
        $usuario = $this->usuario(['email' => 'ana.perez@example.test']);

        $this->guardar($usuario, ['email' => 'ANA.Perez@Example.TEST'])->assertSessionHasNoErrors();

        $this->assertSame('ana.perez@example.test', $usuario->fresh()->email);
        Notification::assertNothingSent();
    }

    // --- Cambiar el correo exige la contraseña actual ---

    public function test_cambiar_el_correo_sin_contrasena_o_con_una_incorrecta_falla(): void
    {
        $usuario = $this->usuario(['email' => 'ana.perez@example.test']);

        $this->guardar($usuario, ['email' => 'ana.nueva@example.test'])
            ->assertSessionHasErrors(['current_password' => 'Para cambiar tu correo escribe tu contraseña actual.']);
        $this->guardar($usuario, ['email' => 'ana.nueva@example.test', 'current_password' => 'No-Es-La-Clave-1'])
            ->assertSessionHasErrors(['current_password' => 'La contraseña actual no es correcta.']);

        $this->assertSame('ana.perez@example.test', $usuario->fresh()->email);
        Notification::assertNothingSent();
    }

    public function test_con_la_contrasena_correcta_cambia_el_correo_avisa_al_anterior_y_queda_registrado(): void
    {
        $usuario = $this->usuario(['email' => 'ana.perez@example.test', 'nombres' => 'Ana']);

        $this->guardar($usuario, ['email' => 'Juana.Nueva@Gmail.com', 'current_password' => self::CLAVE])
            ->assertSessionHasNoErrors()->assertRedirect('/profile');

        $usuario->refresh();
        $this->assertSame('juana.nueva@gmail.com', $usuario->email);
        $this->assertNull($usuario->email_verified_at, 'como en Breeze: el correo nuevo queda sin verificar');

        Notification::assertSentOnDemand(CorreoCambiadoNotification::class, function (CorreoCambiadoNotification $aviso, array $canales, AnonymousNotifiable $destino): bool {
            $correo = $aviso->toMail($destino);
            $this->assertSame('ana.perez@example.test', $destino->routes['mail'], 'el aviso va al correo ANTERIOR');
            $this->assertSame('El correo de tu cuenta cambió – POL-Bolivia', $correo->subject);
            $this->assertSame('Hola, Ana:', $correo->greeting);
            $this->assertContains('El correo de tu cuenta de POL-Bolivia se cambió a ju***@gmail.com. Si no fuiste tú, contacta a la administración.', $correo->introLines);

            return true;
        });

        $registro = $this->contextos('seguridad.correo_cambiado')[0] ?? [];
        $this->assertSame(['usuario_id' => $usuario->id, 'correo_anterior' => 'an***@example.test', 'correo_nuevo' => 'ju***@gmail.com'],
            array_intersect_key($registro, array_flip(['usuario_id', 'correo_anterior', 'correo_nuevo'])));
        $this->assertArrayHasKey('ip', $registro);
        $todo = json_encode($this->registros, JSON_UNESCAPED_UNICODE);
        foreach (['ana.perez@example.test', 'juana.nueva@gmail.com', self::CLAVE] as $sensible) {
            $this->assertStringNotContainsString($sensible, $todo);
        }
    }

    public function test_un_correo_ya_registrado_con_otras_mayusculas_se_rechaza(): void
    {
        User::factory()->create(['email' => 'Otra.Persona@Example.test']);
        $usuario = $this->usuario();

        $this->guardar($usuario, ['email' => 'otra.persona@example.test', 'current_password' => self::CLAVE])
            ->assertSessionHasErrors(['email' => 'Este correo ya está registrado.']);

        // También en el registro (sin sesión: la ruta es solo para invitados).
        auth()->logout();
        $this->post('/register', ['nombres' => 'Ana', 'apellidos' => 'Pérez', 'email' => 'OTRA.persona@example.TEST', 'password' => self::CLAVE, 'password_confirmation' => self::CLAVE])
            ->assertSessionHasErrors(['email' => 'Este correo ya está registrado.']);
    }

    // --- Cambiar la contraseña ---

    public function test_cambiar_la_contrasena_cierra_las_otras_sesiones_mantiene_la_actual_y_avisa(): void
    {
        config(['session.driver' => 'database']);
        $usuario = $this->usuario(['remember_token' => 'recordarme-anterior']);
        $this->sesion('otro-dispositivo-1', $usuario);
        $this->sesion('otro-dispositivo-2', $usuario);
        $otro = $this->usuario();
        $this->sesion('sesion-de-otra-persona', $otro);

        $this->actingAs($usuario)->from('/profile')->put('/password', [
            'current_password' => self::CLAVE, 'password' => 'Nueva-Clave-2026', 'password_confirmation' => 'Nueva-Clave-2026',
        ])->assertSessionHasNoErrors()->assertSessionHas('status', 'password-updated');

        $usuario->refresh();
        $this->assertTrue(Hash::check('Nueva-Clave-2026', $usuario->password));
        $this->assertNotSame('recordarme-anterior', $usuario->remember_token);
        $this->assertFalse(DB::table('sessions')->whereIn('id', ['otro-dispositivo-1', 'otro-dispositivo-2'])->exists(), 'se cierran las otras sesiones');
        $this->assertTrue(DB::table('sessions')->where('id', 'sesion-de-otra-persona')->exists(), 'las de otras personas no');

        // La sesión actual sigue.
        $this->assertAuthenticatedAs($usuario);
        $this->get('/profile')->assertOk();

        Notification::assertSentTo($usuario, ContrasenaCambiadaNotification::class, function (ContrasenaCambiadaNotification $aviso) use ($usuario): bool {
            $this->assertContains('Por seguridad, se cerraron las sesiones que tenías abiertas en otros dispositivos.', $aviso->toMail($usuario)->introLines);

            return true;
        });
        $this->assertSame([$usuario->id], array_column($this->contextos('seguridad.contrasena_cambiada_perfil'), 'usuario_id'));
        $this->assertStringNotContainsString('Nueva-Clave-2026', json_encode($this->registros));
    }

    public function test_la_nueva_contrasena_del_perfil_cumple_la_politica_del_registro(): void
    {
        $usuario = $this->usuario();

        foreach (['Corta7!' => 'La contraseña debe tener al menos 8 caracteres.', 'sinmayusculas123' => 'La contraseña debe incluir al menos una letra mayúscula y una minúscula.', 'SinNumerosAqui' => 'La contraseña debe incluir al menos un número.'] as $clave => $mensaje) {
            $this->actingAs($usuario)->from('/profile')->put('/password', ['current_password' => self::CLAVE, 'password' => $clave, 'password_confirmation' => $clave])
                ->assertSessionHasErrorsIn('updatePassword', ['password' => $mensaje]);
        }

        $this->actingAs($usuario)->from('/profile')->put('/password', ['current_password' => 'No-Es-La-Clave-1', 'password' => 'Nueva-Clave-2026', 'password_confirmation' => 'Nueva-Clave-2026'])
            ->assertSessionHasErrorsIn('updatePassword', ['current_password' => 'La contraseña actual no es correcta.']);

        $this->assertTrue(Hash::check(self::CLAVE, $usuario->fresh()->password));
        Notification::assertNothingSent();
    }

    // --- Límites ---

    public function test_guardar_el_perfil_y_cambiar_la_contrasena_tienen_limite_de_intentos(): void
    {
        $usuario = $this->usuario();

        foreach (range(1, 5) as $intento) {
            $this->guardar($usuario, ['email' => 'otro@example.test', 'current_password' => 'Incorrecta-'.$intento])->assertStatus(302);
        }
        $this->guardar($usuario, ['nombres' => 'Ana'])->assertStatus(429)->assertSee('Demasiadas solicitudes');

        foreach (range(1, 5) as $intento) {
            $this->actingAs($usuario)->from('/profile')->put('/password', ['current_password' => 'Incorrecta-'.$intento, 'password' => 'Nueva-Clave-2026', 'password_confirmation' => 'Nueva-Clave-2026'])->assertStatus(302);
        }
        $this->actingAs($usuario)->from('/profile')->put('/password', ['current_password' => self::CLAVE, 'password' => 'Nueva-Clave-2026', 'password_confirmation' => 'Nueva-Clave-2026'])
            ->assertStatus(429)->assertSee('Demasiadas solicitudes');

        $this->assertTrue(Hash::check(self::CLAVE, $usuario->fresh()->password), 'el intento 6 no se procesa');
    }

    // --- Administrador principal ---

    public function test_el_administrador_principal_edita_sus_propios_datos_desde_mi_perfil(): void
    {
        $principal = $this->usuario();
        $principal->forceFill(['rol' => 'administrador'])->save();
        $principal->forceFill(['es_principal' => true])->save();

        $this->guardar($principal, ['nombres' => 'Lady', 'telefono' => '71234567'])->assertSessionHasNoErrors();
        $this->guardar($principal, ['email' => 'principal.nuevo@example.test', 'current_password' => self::CLAVE])->assertSessionHasNoErrors();

        $principal->refresh();
        $this->assertSame(['Lady', '71234567', 'principal.nuevo@example.test'], [$principal->nombres, $principal->telefono, $principal->email]);
        $this->assertTrue($principal->es_principal);
    }

    // --- Pantallas ---

    public function test_mi_perfil_muestra_el_telefono_y_la_contrasena_actual_solo_al_cambiar_el_correo(): void
    {
        $usuario = $this->usuario(['telefono' => '71234567']);

        $campoOculto = '/x-show="correo\.trim\(\)\.toLowerCase\(\) !== correoActual"\s+x-cloak/';

        $pagina = $this->actingAs($usuario)->get('/profile')->assertOk()
            ->assertSee('Teléfono (opcional)')
            ->assertSee('value="71234567"', false)
            ->assertSee('Contraseña actual')
            ->assertSee('Información personal')
            ->assertDontSee('Profile Information')
            ->assertDontSee('Update Password');
        $this->assertMatchesRegularExpression($campoOculto, $pagina->getContent(), 'oculto hasta que se cambie el correo');

        // Si el cambio de correo falló por la contraseña, el campo se muestra sin esperar a Alpine.
        $this->guardar($usuario, ['email' => 'nuevo@example.test']);
        $pagina = $this->actingAs($usuario)->get('/profile')->assertOk()
            ->assertSee('Para cambiar tu correo escribe tu contraseña actual.');
        $this->assertDoesNotMatchRegularExpression($campoOculto, $pagina->getContent());
    }

    public function test_las_pantallas_de_cuenta_usan_la_identidad_de_pol_bolivia_en_espanol(): void
    {
        $logoPol = 'images/pol-bolivia/logos/POL_02_horizontal_web.png';
        $logoLaravel = 'viewBox="0 0 316 316"';
        $sinVerificar = User::factory()->unverified()->create();

        $pantallas = [
            [null, '/forgot-password', '¿Olvidaste tu contraseña? | POL-Bolivia', ['¿Olvidaste tu contraseña?', 'Correo electrónico', 'Enviar enlace', 'Volver a iniciar sesión']],
            [null, '/reset-password/token-de-ejemplo?email=ana%40example.test', 'Elige una nueva contraseña | POL-Bolivia', ['Elige una nueva contraseña', 'Nueva contraseña', 'Confirma la nueva contraseña', 'Guardar contraseña']],
            [$this->usuario(), '/confirm-password', 'Confirma tu contraseña | POL-Bolivia', ['Confirma tu contraseña', 'Esta es una sección protegida.', 'Confirmar']],
            [$sinVerificar, '/verify-email', 'Verifica tu correo | POL-Bolivia', ['Verifica tu correo', 'Reenviar el correo de verificación', 'Cerrar sesión']],
        ];

        foreach ($pantallas as [$usuario, $ruta, $titulo, $textos]) {
            $respuesta = ($usuario ? $this->actingAs($usuario) : $this)->get($ruta)->assertOk()
                ->assertSee("<title>{$titulo}</title>", false)
                ->assertSee($logoPol, false)
                ->assertDontSee($logoLaravel, false);
            foreach ($textos as $texto) {
                $respuesta->assertSee($texto);
            }
            foreach (['Forgot your password', 'Email Password Reset Link', 'Reset Password', 'Confirm Password', 'Resend Verification Email', 'Log Out', 'Laravel'] as $ingles) {
                $respuesta->assertDontSee($ingles);
            }
            // Cada pantalla con su propia sesión (AuthenticateSession guarda la huella de la contraseña de la anterior).
            auth()->logout();
            $this->flushSession();
        }
    }

    private function usuario(array $atributos = []): User
    {
        return User::factory()->create(['password' => self::CLAVE, ...$atributos]);
    }

    /** Guarda «Mi perfil» con los datos actuales de $usuario más $cambios. */
    private function guardar(User $usuario, array $cambios)
    {
        return $this->actingAs($usuario)->from('/profile')->patch('/profile', [
            'nombres' => $usuario->nombres, 'apellidos' => $usuario->apellidos, 'email' => $usuario->email, 'telefono' => $usuario->telefono, ...$cambios,
        ]);
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
