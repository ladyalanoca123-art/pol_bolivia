<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\PostulacionVoluntariado;
use App\Models\SolicitudAdopcion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\CuestionarioAdopcionDatos;
use Tests\Support\CuestionarioVoluntariadoDatos;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private const HISTORIAL = 'No podemos eliminar tu cuenta porque tiene historial registrado (solicitudes o postulaciones) '
        .'que POL-Bolivia debe conservar. Si ya no deseas usarla, comunícate con la fundación para desactivarla.';

    /** @var list<array> Contexto de cada "seguridad.eliminacion_cuenta" escrito en el log. */
    private array $eliminaciones = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Captura lo que se escribe en el canal "seguridad" sin escribir archivos.
        Event::listen(MessageLogged::class, function (MessageLogged $evento): void {
            if ($evento->message === 'seguridad.eliminacion_cuenta') {
                $this->eliminaciones[] = $evento->context;
            }
        });
    }

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'nombres' => 'Test',
                'apellidos' => 'User',
                'email' => 'test@example.com',
                // Cambiar el correo exige la contraseña actual (ProfileUpdateRequest).
                'current_password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test', $user->nombres);
        $this->assertSame('User', $user->apellidos);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'nombres' => 'Test',
                'apellidos' => 'User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
        $this->assertTrue($this->eliminacionAuditada($user), 'la eliminación queda en la auditoría');
    }

    public function test_un_administrador_no_puede_eliminar_su_cuenta(): void
    {
        $this->assertNoPuedeEliminarSuCuenta(User::factory()->create(['rol' => 'administrador']));
    }

    public function test_un_voluntario_no_puede_eliminar_su_cuenta(): void
    {
        $this->assertNoPuedeEliminarSuCuenta(User::factory()->create(['rol' => 'voluntario']));
    }

    public function test_administradores_y_voluntarios_ven_un_aviso_en_lugar_del_formulario(): void
    {
        $avisos = [
            'administrador' => 'Las cuentas de administración no se eliminan desde el perfil.',
            'voluntario' => 'Tu cuenta de voluntario no se puede eliminar desde el perfil. Comunícate con POL-Bolivia para gestionarla.',
        ];

        foreach ($avisos as $rol => $aviso) {
            $this->actingAs(User::factory()->create(['rol' => $rol]))->get('/profile')->assertOk()
                ->assertSee('Zona de riesgo')
                ->assertSee($aviso)
                ->assertDontSee('confirm-user-deletion')
                ->assertDontSee('name="_method" value="delete"', false);
        }
    }

    public function test_un_ciudadano_con_una_solicitud_de_adopcion_conserva_su_cuenta(): void
    {
        $ciudadano = User::factory()->create();
        $solicitud = SolicitudAdopcion::create([...CuestionarioAdopcionDatos::registro(), 'solicitante_id' => $ciudadano->id, 'mascota_id' => $this->mascota()->id, 'estado' => 'pendiente']);

        $this->assertConservaSuCuentaPorHistorial($ciudadano);
        $this->assertNotNull($solicitud->fresh(), 'la solicitud se conserva');
    }

    public function test_un_ciudadano_con_una_postulacion_de_voluntariado_conserva_su_cuenta(): void
    {
        $ciudadano = User::factory()->create();
        $postulacion = PostulacionVoluntariado::create([...CuestionarioVoluntariadoDatos::registro(), 'usuario_id' => $ciudadano->id, 'estado' => 'pendiente']);

        $this->assertConservaSuCuentaPorHistorial($ciudadano);
        $this->assertNotNull($postulacion->fresh(), 'la postulación se conserva');
    }

    public function test_un_registro_relacionado_no_previsto_tambien_se_informa_sin_error_500(): void
    {
        // Una clave foránea que la comprobación previa no conoce: PostgreSQL rechazaría el DELETE con SQLSTATE 23503.
        // Se fuerza ese error al borrar (sin crear tablas: no se toca el esquema ni siquiera en la prueba).
        $ciudadano = User::factory()->create();
        User::deleting(fn () => throw $this->errorDeBaseDeDatos('23503', 'SQLSTATE[23503]: Foreign key violation: delete on "usuarios" violates foreign key constraint'));

        $this->assertConservaSuCuentaPorHistorial($ciudadano, previa: false);
    }

    public function test_un_error_inesperado_no_se_presenta_como_historial(): void
    {
        $ciudadano = User::factory()->create();
        User::deleting(fn () => throw $this->errorDeBaseDeDatos('23505', 'SQLSTATE[23505]: Unique violation'));

        // Con el manejo normal de Laravel es un error 500, no el mensaje de historial.
        $this->actingAs($ciudadano)->from('/profile')->delete('/profile', ['password' => 'password'])
            ->assertStatus(500)
            ->assertSessionHasNoErrors();

        // Y es exactamente ese error el que sale del controlador, sin transformarse.
        $this->withoutExceptionHandling();

        try {
            $this->delete('/profile', ['password' => 'password']);
            $this->fail('El error inesperado debía propagarse.');
        } catch (QueryException $exception) {
            $this->assertSame('23505', $exception->getCode());
        }

        $this->assertNotNull($ciudadano->fresh());
        $this->assertAuthenticatedAs($ciudadano);
    }

    public function test_el_sexto_intento_en_un_minuto_recibe_429_en_espanol(): void
    {
        $ciudadano = User::factory()->create();

        foreach (range(1, 5) as $intento) {
            $this->actingAs($ciudadano)->from('/profile')->delete('/profile', ['password' => 'Clave-Equivocada-'.$intento])
                ->assertRedirect('/profile')
                ->assertSessionHasErrorsIn('userDeletion', 'password');
        }

        $this->actingAs($ciudadano)->from('/profile')->delete('/profile', ['password' => 'password'])
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertSee('Demasiadas solicitudes')
            ->assertSee('Has realizado demasiadas solicitudes. Espera un momento antes de volver a intentarlo.');

        $this->assertNotNull($ciudadano->fresh(), 'el sexto intento no se procesa aunque la contraseña sea correcta');
        $this->assertSame(
            [...array_fill(0, 5, 'contrasena_incorrecta'), 'limite_de_intentos'],
            array_column($this->eliminaciones, 'resultado'),
        );

        // El límite es por usuario: otra cuenta puede intentarlo.
        $this->actingAs(User::factory()->create())->from('/profile')->delete('/profile', ['password' => 'password'])->assertRedirect('/');
    }

    public function test_el_log_de_seguridad_registra_cada_resultado_sin_la_contrasena(): void
    {
        $administrador = User::factory()->create(['rol' => 'administrador']);
        $voluntario = User::factory()->create(['rol' => 'voluntario']);
        $conHistorial = User::factory()->create();
        PostulacionVoluntariado::create([...CuestionarioVoluntariadoDatos::registro(), 'usuario_id' => $conHistorial->id, 'estado' => 'pendiente']);
        $sinHistorial = User::factory()->create();

        $intentos = [
            [$administrador, 'password', 'bloqueada_por_rol'],
            [$voluntario, 'password', 'bloqueada_por_rol'],
            [$sinHistorial, 'Clave-Secreta-Escrita-123', 'contrasena_incorrecta'],
            [$conHistorial, 'password', 'bloqueada_por_historial'],
            [$sinHistorial, 'password', 'eliminada'],
        ];

        foreach ($intentos as [$usuario, $clave]) {
            $this->actingAs($usuario)->from('/profile')->delete('/profile', ['password' => $clave]);
        }

        $this->assertCount(count($intentos), $this->eliminaciones);

        foreach ($intentos as $i => [$usuario, $clave, $resultado]) {
            $registro = $this->eliminaciones[$i];

            $this->assertSame('eliminacion_cuenta', $registro['evento']);
            $this->assertSame($usuario->id, $registro['usuario_id']);
            $this->assertSame($usuario->rol, $registro['rol']);
            $this->assertSame($resultado, $registro['resultado']);
            $this->assertSame('127.0.0.1', $registro['ip']);
            $this->assertSame([], array_diff(array_keys($registro), ['evento', 'usuario_id', 'rol', 'resultado', 'ip', 'user_agent', 'fecha']));

            // Ni la contraseña escrita ni la real aparecen en ninguna parte del registro.
            $texto = json_encode($registro, JSON_UNESCAPED_UNICODE);
            $this->assertStringNotContainsString($clave, $texto);
            $this->assertStringNotContainsString('Clave-Secreta-Escrita-123', $texto);
        }
    }

    public function test_el_formulario_de_eliminacion_esta_en_espanol(): void
    {
        $this->actingAs(User::factory()->create())->get('/profile')->assertOk()
            ->assertSee('Eliminar cuenta')
            ->assertSee('¿Seguro que quieres eliminar tu cuenta?')
            ->assertSee('Tu cuenta y tus datos personales se eliminarán. Esta acción no se puede deshacer. Escribe tu contraseña para confirmar.')
            ->assertSee('placeholder="Contraseña"', false)
            ->assertSee('Cancelar')
            ->assertDontSee('Delete Account')
            ->assertDontSee('Are you sure')
            ->assertDontSee('all of its resources and data will be permanently deleted');
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    private function assertNoPuedeEliminarSuCuenta(User $usuario): void
    {
        $this->actingAs($usuario)->from('/profile')->delete('/profile', ['password' => 'password'])->assertForbidden();

        $this->assertNotNull($usuario->fresh(), 'la cuenta sigue existiendo');
        $this->assertAuthenticatedAs($usuario);
        $this->assertFalse($this->eliminacionAuditada($usuario));
    }

    /** $previa: el historial lo detecta la comprobación previa, así que ni siquiera se intenta el DELETE. */
    private function assertConservaSuCuentaPorHistorial(User $ciudadano, bool $previa = true): void
    {
        // "deleting" se dispara antes del DELETE (DB::listen solo ve las consultas que terminan bien).
        $intentos = 0;
        User::deleting(function () use (&$intentos): void {
            $intentos++;
        });

        $this->actingAs($ciudadano)->from('/profile')->delete('/profile', ['password' => 'password'])
            ->assertRedirect('/profile')
            ->assertSessionHasErrorsIn('userDeletion', ['cuenta' => self::HISTORIAL]);

        if ($previa) {
            $this->assertSame(0, $intentos, 'con historial no se intenta el DELETE');
        }

        $this->assertNotNull($ciudadano->fresh(), 'la cuenta sigue existiendo');
        $this->assertAuthenticatedAs($ciudadano);
        $this->assertFalse($this->eliminacionAuditada($ciudadano), 'no se audita una eliminación que no ocurrió');

        // El modal se abre solo y muestra el mensaje.
        $this->get('/profile')->assertOk()->assertSee(self::HISTORIAL)->assertSee('role="alert"', false);
    }

    /** QueryException como la que lanza PostgreSQL al borrar, con el SQLSTATE indicado. */
    private function errorDeBaseDeDatos(string $sqlstate, string $mensaje): QueryException
    {
        // PDO guarda el SQLSTATE como texto en Exception::$code (el constructor solo admite enteros).
        $error = new \PDOException($mensaje);
        (new \ReflectionProperty(\Exception::class, 'code'))->setValue($error, $sqlstate);

        return new QueryException('pgsql', 'delete from "usuarios" where "id" = ?', [], $error);
    }

    private function eliminacionAuditada(User $usuario): bool
    {
        return Auditoria::where('modelo', 'User')->where('modelo_id', $usuario->id)->where('accion', 'eliminado')->exists();
    }

    /** Mascota disponible con su foto principal, registrada por un administrador. */
    private function mascota(): Mascota
    {
        $admin = User::factory()->create(['rol' => 'administrador']);

        return DB::transaction(function () use ($admin): Mascota {
            $mascota = Mascota::create(['nombre' => 'Luna', 'especie' => 'perro', 'raza' => 'Mestiza', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $admin->id]);
            FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => 'mascotas/principal.jpg', 'es_principal' => true, 'orden' => 1]);

            return $mascota;
        });
    }
}