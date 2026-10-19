<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\User;
use Database\Seeders\AdministradorInicialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Administradores: el seeder inicial no reinicia contraseñas, y los comandos pol:promover-administrador y
 * pol:retirar-administrador cambian el rol con sus validaciones, auditoría y registro de seguridad.
 * Usa pol_bolivia_test (marca POL-BOLIVIA-PRUEBAS, tests/TestCase.php).
 */
class GestionAdministradoresTest extends TestCase
{
    use RefreshDatabase;

    private const VARIABLE = 'INITIAL_ADMIN_PASSWORD';

    /** @var list<array> Contexto de cada "seguridad.rol_administrador". */
    private array $cambiosDeRol = [];

    /** Valor de INITIAL_ADMIN_PASSWORD antes de la prueba (el .env del proyecto se carga): se restaura al final. */
    private array $variableOriginal = [];

    protected function setUp(): void
    {
        parent::setUp();

        Event::listen(MessageLogged::class, function (MessageLogged $evento): void {
            if ($evento->message === 'seguridad.rol_administrador') {
                $this->cambiosDeRol[] = $evento->context;
            }
        });

        $this->variableOriginal = [
            'server' => array_key_exists(self::VARIABLE, $_SERVER) ? $_SERVER[self::VARIABLE] : null,
            'env' => array_key_exists(self::VARIABLE, $_ENV) ? $_ENV[self::VARIABLE] : null,
        ];
    }

    protected function tearDown(): void
    {
        foreach (['server' => '_SERVER', 'env' => '_ENV'] as $clave => $global) {
            if ($this->variableOriginal[$clave] === null) {
                unset($GLOBALS[$global][self::VARIABLE]);
            } else {
                $GLOBALS[$global][self::VARIABLE] = $this->variableOriginal[$clave];
            }
        }

        parent::tearDown();
    }

    // --- Seeder del administrador inicial ---

    public function test_el_seeder_crea_el_administrador_inicial_si_no_existe(): void
    {
        $this->definirClave('Clave-Inicial-Prueba-1');

        $this->artisan('db:seed', ['--class' => AdministradorInicialSeeder::class, '--force' => true])
            ->expectsOutputToContain('Administrador inicial creado.')
            ->assertSuccessful();

        $admin = User::query()->where('email', AdministradorInicialSeeder::CORREO)->sole();
        $this->assertSame('administrador', $admin->rol);
        $this->assertTrue($admin->estado);
        $this->assertTrue(Hash::check('Clave-Inicial-Prueba-1', $admin->password));
    }

    public function test_si_ya_existe_el_seeder_no_cambia_la_contrasena_ni_ningun_dato(): void
    {
        $existente = User::factory()->create([
            'email' => AdministradorInicialSeeder::CORREO, 'nombres' => 'Nombre Real', 'password' => 'Clave-Real-Que-No-Cambia-9',
        ]);
        $existente->forceFill(['rol' => 'administrador'])->save();
        $antes = $existente->fresh()->getAttributes();
        $this->definirClave('Otra-Clave-Distinta-2');

        foreach ([1, 2] as $ejecucion) {
            $this->artisan('db:seed', ['--class' => AdministradorInicialSeeder::class, '--force' => true])
                ->expectsOutputToContain('El administrador inicial ya existe; no se modificó.')
                ->assertSuccessful();
        }

        $this->assertSame($antes, $existente->fresh()->getAttributes(), 'ningún dato cambia, tampoco updated_at');
        $this->assertTrue(Hash::check('Clave-Real-Que-No-Cambia-9', $existente->fresh()->password));
        $this->assertSame(1, User::query()->where('email', AdministradorInicialSeeder::CORREO)->count());
    }

    public function test_el_seeder_falla_sin_crear_nada_si_la_clave_esta_vacia_o_es_corta(): void
    {
        foreach (['', 'Corta7!'] as $clave) {
            $this->definirClave($clave);

            try {
                (new AdministradorInicialSeeder)->run();
                $this->fail('El seeder debía fallar.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('No se creó el administrador inicial: define INITIAL_ADMIN_PASSWORD en el archivo .env con al menos 8 caracteres.', $exception->getMessage());
            }

            $this->assertFalse(User::query()->where('email', AdministradorInicialSeeder::CORREO)->exists());
        }
    }

    // --- pol:promover-administrador ---

    public function test_promover_un_correo_inexistente_falla(): void
    {
        $this->artisan('pol:promover-administrador', ['correo' => 'nadie@prueba.test', '--force' => true])
            ->expectsOutputToContain('No existe ninguna cuenta con ese correo.')
            ->assertFailed();

        $this->assertSame([], $this->cambiosDeRol);
    }

    public function test_promover_una_cuenta_desactivada_falla(): void
    {
        $ciudadano = User::factory()->create(['estado' => false]);

        $this->artisan('pol:promover-administrador', ['correo' => $ciudadano->email, '--force' => true])
            ->expectsOutputToContain('está desactivada; no se puede promover')
            ->assertFailed();

        $this->assertSame('ciudadano', $ciudadano->fresh()->rol);
    }

    public function test_promover_a_quien_ya_es_administrador_no_cambia_nada(): void
    {
        $admin = $this->administrador();
        $antes = $admin->fresh()->getAttributes();

        $this->artisan('pol:promover-administrador', ['correo' => $admin->email, '--force' => true])
            ->expectsOutputToContain('ya es administrador; no se hizo ningún cambio.')
            ->assertSuccessful();

        $this->assertSame($antes, $admin->fresh()->getAttributes());
        $this->assertSame([], $this->cambiosDeRol);
    }

    public function test_un_voluntario_no_se_promueve_y_se_explica_por_que(): void
    {
        $voluntario = User::factory()->create(['rol' => 'voluntario']);

        $this->artisan('pol:promover-administrador', ['correo' => $voluntario->email, '--force' => true])
            ->expectsOutputToContain("Solo se pueden promover ciudadanos y {$voluntario->email} es voluntario. Primero hay que darlo de baja desde «Voluntarios»")
            ->assertFailed();

        $this->assertSame('voluntario', $voluntario->fresh()->rol);
    }

    public function test_promover_pide_confirmacion_y_con_no_no_cambia_nada(): void
    {
        $ciudadano = User::factory()->create();

        $this->artisan('pol:promover-administrador', ['correo' => $ciudadano->email])
            ->expectsTable(['Nombre', 'Correo', 'Rol actual', 'Estado'], [[trim("{$ciudadano->nombres} {$ciudadano->apellidos}"), $ciudadano->email, 'Ciudadano', 'Activa']])
            ->expectsQuestion("¿Convertir a {$ciudadano->email} en administrador? Escribe «sí» para confirmar o «no» para cancelar", 'no')
            ->expectsOutputToContain('Cancelado: no se hizo ningún cambio.')
            ->assertFailed();

        $this->assertSame('ciudadano', $ciudadano->fresh()->rol);
    }

    public function test_promover_con_si_convierte_en_administrador_sin_tocar_la_contrasena_y_queda_registrado(): void
    {
        $ciudadano = User::factory()->create(['email' => 'Nueva.Admin@Prueba.test']);
        $clave = $ciudadano->password;

        $this->artisan('pol:promover-administrador', ['correo' => 'nueva.admin@prueba.test'])
            ->expectsQuestion("¿Convertir a {$ciudadano->email} en administrador? Escribe «sí» para confirmar o «no» para cancelar", 'sí')
            ->expectsOutputToContain('ahora es administrador')
            ->assertSuccessful();

        $ciudadano->refresh();
        $this->assertSame('administrador', $ciudadano->rol);
        $this->assertSame($clave, $ciudadano->password, 'la contraseña no cambia');
        $this->assertSame([['evento' => 'rol_administrador', 'usuario_id' => $ciudadano->id, 'accion' => 'promovido', 'rol_anterior' => 'ciudadano', 'rol_nuevo' => 'administrador']], $this->sinFechaNiAgente($this->cambiosDeRol));
        $this->assertCambioDeRolAuditado($ciudadano, 'ciudadano', 'administrador');

        // Desde ya entra al panel de administración.
        $this->actingAs($ciudadano)->get(route('admin.usuarios.index'))->assertOk();
    }

    // --- pol:retirar-administrador ---

    public function test_no_se_retira_al_ultimo_administrador_activo(): void
    {
        $unico = $this->administrador();
        // Un administrador desactivado no cuenta como activo.
        $this->administrador(['estado' => false]);

        $this->artisan('pol:retirar-administrador', ['correo' => $unico->email, '--force' => true])
            ->expectsOutputToContain('es el último administrador activo')
            ->assertFailed();

        $this->assertSame('administrador', $unico->fresh()->rol);
        $this->assertSame([], $this->cambiosDeRol);
    }

    public function test_con_dos_administradores_se_retira_uno_cerrando_sus_sesiones_y_su_recordarme(): void
    {
        config(['session.driver' => 'database']);
        $sigue = $this->administrador();
        $retirado = $this->administrador();
        $retirado->forceFill(['remember_token' => 'token-recordarme-123'])->save();
        $this->sesion('sesion-retirado-1', $retirado);
        $this->sesion('sesion-retirado-2', $retirado);
        $this->sesion('sesion-del-otro', $sigue);

        $this->artisan('pol:retirar-administrador', ['correo' => $retirado->email])
            ->expectsQuestion("¿Quitar el rol de administrador a {$retirado->email}? Pasará a ser ciudadano. Escribe «sí» para confirmar o «no» para cancelar", 'si')
            ->expectsOutputToContain('ya no es administrador (ahora es ciudadano)')
            ->assertSuccessful();

        $retirado->refresh();
        $this->assertSame('ciudadano', $retirado->rol);
        $this->assertNull($retirado->remember_token);
        $this->assertSame(['sesion-del-otro'], DB::table('sessions')->pluck('id')->all(), 'solo se cierran sus sesiones');
        $this->assertSame('administrador', $sigue->fresh()->rol);
        $this->assertSame([['evento' => 'rol_administrador', 'usuario_id' => $retirado->id, 'accion' => 'retirado', 'rol_anterior' => 'administrador', 'rol_nuevo' => 'ciudadano']], $this->sinFechaNiAgente($this->cambiosDeRol));
        $this->assertCambioDeRolAuditado($retirado, 'administrador', 'ciudadano');

        // Pierde el acceso de administrador de inmediato.
        $this->actingAs($retirado)->get(route('admin.usuarios.index'))->assertForbidden();
    }

    public function test_retirar_rechaza_otro_rol_nuevo_y_a_quien_no_es_administrador(): void
    {
        $this->administrador();
        $admin = $this->administrador();
        $ciudadano = User::factory()->create();

        $this->artisan('pol:retirar-administrador', ['correo' => $admin->email, '--rol' => 'voluntario', '--force' => true])
            ->expectsOutputToContain('El rol nuevo solo puede ser «ciudadano»')
            ->assertFailed();
        $this->artisan('pol:retirar-administrador', ['correo' => $ciudadano->email, '--force' => true])
            ->expectsOutputToContain('no es administrador; no se hizo ningún cambio.')
            ->assertFailed();

        $this->assertSame('administrador', $admin->fresh()->rol);
        $this->assertSame([], $this->cambiosDeRol);
    }

    public function test_retirar_pide_confirmacion_y_con_no_no_cambia_nada(): void
    {
        $this->administrador();
        $admin = $this->administrador();

        $this->artisan('pol:retirar-administrador', ['correo' => $admin->email])
            ->expectsQuestion("¿Quitar el rol de administrador a {$admin->email}? Pasará a ser ciudadano. Escribe «sí» para confirmar o «no» para cancelar", 'cancelar')
            ->expectsOutputToContain('Cancelado: no se hizo ningún cambio.')
            ->assertFailed();

        $this->assertSame('administrador', $admin->fresh()->rol);
    }

    private function definirClave(string $clave): void
    {
        $_SERVER[self::VARIABLE] = $clave;
        $_ENV[self::VARIABLE] = $clave;
    }

    private function administrador(array $atributos = []): User
    {
        $usuario = User::factory()->create($atributos);
        $usuario->forceFill(['rol' => 'administrador'])->save();

        return $usuario->fresh();
    }

    private function sesion(string $id, User $usuario): void
    {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $usuario->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'prueba', 'payload' => base64_encode('a:0:{}'), 'last_activity' => now()->getTimestamp()]);
    }

    private function sinFechaNiAgente(array $registros): array
    {
        return array_map(fn (array $registro) => array_diff_key($registro, array_flip(['fecha', 'user_agent', 'ip'])), $registros);
    }

    private function assertCambioDeRolAuditado(User $usuario, string $anterior, string $nuevo): void
    {
        $auditoria = Auditoria::query()->where('modelo', 'User')->where('modelo_id', $usuario->id)->where('accion', 'actualizado')->latest('id')->firstOrFail();

        $this->assertSame($anterior, $auditoria->valores_anteriores['rol']);
        $this->assertSame($nuevo, $auditoria->valores_nuevos['rol']);
        $this->assertArrayNotHasKey('password', $auditoria->valores_nuevos);
        $this->assertArrayNotHasKey('remember_token', $auditoria->valores_nuevos);
    }
}
