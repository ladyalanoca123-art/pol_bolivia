<?php

namespace Tests\Feature\Auth;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Una cuenta desactivada no puede seguir usando una sesión abierta ni volver a entrar con
 * «Recordarme» (App\Http\Middleware\ExpulsarCuentaDesactivada).
 */
class CuentaDesactivadaTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'Clave-Prueba-123';

    private const MENSAJE = 'Tu cuenta está desactivada. Comunícate con la fundación.';

    public function test_una_cuenta_activa_conserva_su_sesion(): void
    {
        $usuario = $this->usuario();

        $this->actingAs($usuario)->get('/profile')->assertOk();
        $this->get('/profile')->assertOk();

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_una_cuenta_desactivada_pierde_la_sesion_abierta(): void
    {
        $usuario = $this->usuario();
        $this->actingAs($usuario)->get('/profile')->assertOk();

        $this->desactivar($usuario);

        $this->get('/profile')->assertForbidden()->assertSee(self::MENSAJE);
        $this->assertGuest();

        // La siguiente petición ya llega como visitante.
        $this->get('/profile')->assertRedirect(route('login'));
    }

    public function test_una_cuenta_desactivada_no_puede_cambiar_ni_borrar_su_perfil(): void
    {
        $usuario = $this->usuario();
        $this->actingAs($usuario);
        $this->desactivar($usuario);

        $this->patch('/profile', ['nombres' => 'Otro', 'apellidos' => 'Nombre', 'email' => 'nuevo@example.com'])->assertForbidden();
        $this->assertGuest();
        $this->assertSame($usuario->email, $usuario->fresh()->email);

        $this->actingAs($usuario->fresh())->delete('/profile', ['password' => self::CLAVE])->assertForbidden();
        $this->assertNotNull($usuario->fresh());
    }

    public function test_una_cuenta_desactivada_no_vuelve_a_entrar_con_recordarme(): void
    {
        $usuario = $this->usuario();
        $recordador = app('auth')->guard()->getRecallerName();
        $cookie = $this->post('/login', ['email' => $usuario->email, 'password' => self::CLAVE, 'remember' => 'on'])
            ->getCookie($recordador, false)->getValue();
        $tokenAnterior = $usuario->fresh()->remember_token;

        $this->desactivar($usuario);

        // Otro dispositivo: sin sesión, solo con la cookie «Recordarme».
        $this->otroDispositivo($recordador, $cookie)->assertForbidden()->assertCookieExpired($recordador);
        $this->assertGuest();
        $this->assertNotSame($tokenAnterior, $usuario->fresh()->remember_token);

        // La misma cookie ya no autentica, aunque la cuenta se reactive después.
        $usuario->fresh()->forceFill(['estado' => true])->save();
        $this->otroDispositivo($recordador, $cookie)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_una_cuenta_desactivada_no_puede_iniciar_sesion_con_recordarme(): void
    {
        $usuario = $this->usuario(['estado' => false]);
        $recordador = app('auth')->guard()->getRecallerName();
        $token = $usuario->remember_token;

        $this->post('/login', ['email' => $usuario->email, 'password' => self::CLAVE, 'remember' => 'on'])
            ->assertSessionHasErrors(['email' => self::MENSAJE])
            ->assertCookieMissing($recordador);

        $this->assertGuest();
        $this->assertSame($token, $usuario->fresh()->remember_token);
    }

    public function test_la_expulsion_no_afecta_a_otras_cuentas_ni_registra_el_token_en_la_auditoria(): void
    {
        $desactivada = $this->usuario();
        $otra = $this->usuario();
        $otra->forceFill(['remember_token' => 'token-de-otra-cuenta'])->save();
        $this->actingAs($desactivada);
        $this->desactivar($desactivada);
        $auditorias = Auditoria::count();

        $this->get('/profile')->assertForbidden();

        $this->assertSame($auditorias, Auditoria::count(), 'renovar el remember_token no genera auditoría');
        $this->assertSame('token-de-otra-cuenta', $otra->fresh()->remember_token);
        $this->actingAs($otra->fresh())->get('/profile')->assertOk();
    }

    public function test_usuarios_y_administradores_activos_mantienen_sus_permisos(): void
    {
        $ciudadano = $this->usuario(['rol' => 'ciudadano']);
        $administrador = $this->usuario(['rol' => 'administrador']);

        $this->actingAs($ciudadano)->get(route('solicitudes-adopcion.index'))->assertOk();
        $this->actingAs($ciudadano)->get(route('admin.dashboard'))->assertForbidden();

        // Otro navegador: AuthenticateSession guarda en la sesión el hash de la contraseña de quien la abrió.
        $this->flushSession();
        $this->actingAs($administrador)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($administrador)->get(route('admin.usuarios.index'))->assertOk();
        $this->assertAuthenticatedAs($administrador);
    }

    public function test_un_administrador_desactivado_pierde_el_acceso_al_panel(): void
    {
        $administrador = $this->usuario(['rol' => 'administrador']);
        $this->actingAs($administrador)->get(route('admin.dashboard'))->assertOk();

        $this->desactivar($administrador);

        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->assertGuest();
    }

    private function usuario(array $atributos = []): User
    {
        return User::factory()->create(['password' => Hash::make(self::CLAVE), ...$atributos]);
    }

    /** Desactiva la cuenta como lo haría el administrador, mientras su sesión sigue abierta. */
    private function desactivar(User $usuario): void
    {
        $usuario->forceFill(['estado' => false])->save();
    }

    private function otroDispositivo(string $recordador, string $cookie)
    {
        $this->flushSession();
        app('auth')->forgetGuards();

        return $this->withUnencryptedCookie($recordador, $cookie)->get('/profile');
    }
}
