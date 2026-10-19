<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\User;
use App\Support\EjecutorConsola;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Administrador principal (usuarios.es_principal): uno solo, protegido en PostgreSQL, en la web y en los comandos,
 * y transferible con pol:transferir-principal. Usa pol_bolivia_test (marca POL-BOLIVIA-PRUEBAS, tests/TestCase.php).
 */
class AdministradorPrincipalTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, list<array>> Contexto de los eventos de "seguridad" por mensaje. */
    private array $registros = [];

    protected function setUp(): void
    {
        parent::setUp();

        Event::listen(MessageLogged::class, function (MessageLogged $evento): void {
            if (str_starts_with($evento->message, 'seguridad.')) {
                $this->registros[$evento->message][] = $evento->context;
            }
        });
    }

    // --- PostgreSQL ---

    public function test_solo_puede_existir_un_administrador_principal(): void
    {
        $this->principal();
        $otro = $this->administrador();

        $error = $this->errorAlGuardar(fn () => $otro->forceFill(['es_principal' => true])->save());

        $this->assertSame('23505', $error?->getCode(), 'índice único parcial idx_un_administrador_principal');
        $this->assertSame(1, User::query()->where('es_principal', true)->count());
    }

    public function test_postgresql_impide_desactivar_quitar_el_rol_o_eliminar_al_principal(): void
    {
        $principal = $this->principal();
        $ciudadano = User::factory()->create();

        $intentos = [
            'desactivar' => fn () => DB::table('usuarios')->where('id', $principal->id)->update(['estado' => false]),
            'quitar el rol' => fn () => DB::table('usuarios')->where('id', $principal->id)->update(['rol' => 'ciudadano']),
            'eliminar' => fn () => DB::table('usuarios')->where('id', $principal->id)->delete(),
            'marcar a un ciudadano' => fn () => DB::table('usuarios')->where('id', $ciudadano->id)->update(['es_principal' => true]),
        ];

        foreach ($intentos as $intento => $operacion) {
            $error = $this->errorAlGuardar($operacion);
            $this->assertSame('P0001', $error?->getCode(), "PostgreSQL debía impedir: {$intento}");
        }

        $principal->refresh();
        $this->assertTrue($principal->es_principal && $principal->estado && $principal->hasRole('administrador'));
        $this->assertFalse($ciudadano->fresh()->es_principal);
    }

    // --- Web ---

    public function test_otro_administrador_no_puede_editar_ni_desactivar_al_principal(): void
    {
        $principal = $this->principal();
        $antes = $principal->fresh()->getAttributes();
        $otro = $this->administrador();

        $this->actingAs($otro)->get(route('admin.usuarios.edit', $principal))->assertForbidden();
        $this->actingAs($otro)->patch(route('admin.usuarios.update', $principal), ['nombres' => 'Cambiado', 'apellidos' => 'Cambiado', 'email' => $principal->email])->assertForbidden();
        $this->actingAs($otro)->patch(route('admin.usuarios.estado.update', $principal), ['estado' => 0])->assertForbidden();

        $this->assertSame($antes, $principal->fresh()->getAttributes());
    }

    public function test_la_lista_y_el_detalle_muestran_la_etiqueta_y_no_ofrecen_editar_al_principal(): void
    {
        $principal = $this->principal();
        $otro = $this->administrador();

        $this->actingAs($otro)->get(route('admin.usuarios.index'))->assertOk()
            ->assertSee('Administrador principal')
            ->assertDontSee('href="'.route('admin.usuarios.edit', $principal).'"', false)
            ->assertSee('href="'.route('admin.usuarios.edit', $otro).'"', false);

        $this->actingAs($otro)->get(route('admin.usuarios.show', $principal))->assertOk()
            ->assertSee('Administrador principal')
            ->assertSee('Al administrador principal solo lo edita él mismo.')
            ->assertDontSee('Editar usuario');
    }

    public function test_el_principal_puede_editar_sus_propios_datos(): void
    {
        $principal = $this->principal();

        $this->actingAs($principal)->get(route('admin.usuarios.show', $principal))->assertOk()->assertSee('Editar usuario');
        $this->actingAs($principal)->get(route('admin.usuarios.edit', $principal))->assertOk();
        $this->actingAs($principal)->patch(route('admin.usuarios.update', $principal), ['nombres' => 'Nuevo', 'apellidos' => 'Nombre', 'email' => $principal->email])
            ->assertSessionHasNoErrors();

        $this->assertSame('Nuevo', $principal->fresh()->nombres);
    }

    public function test_el_principal_no_puede_eliminar_su_cuenta_desde_el_perfil(): void
    {
        $principal = $this->principal();

        $this->actingAs($principal)->get('/profile')->assertOk()
            ->assertSee('Las cuentas de administración no se eliminan desde el perfil.')
            ->assertDontSee('confirm-user-deletion');
        $this->actingAs($principal)->from('/profile')->delete('/profile', ['password' => 'password'])->assertForbidden();

        $this->assertNotNull($principal->fresh());
    }

    // --- Comandos ---

    public function test_no_se_retira_al_administrador_principal(): void
    {
        $principal = $this->principal();
        $this->administrador();

        $this->artisan('pol:retirar-administrador', ['correo' => $principal->email, '--force' => true])
            ->expectsOutputToContain("No se puede retirar a {$principal->email}: es el administrador principal.")
            ->assertFailed();

        $principal->refresh();
        $this->assertTrue($principal->es_principal);
        $this->assertSame('administrador', $principal->rol);
        $this->assertArrayNotHasKey('seguridad.rol_administrador', $this->registros);
    }

    public function test_el_traspaso_mueve_la_marca_y_el_anterior_sigue_siendo_administrador(): void
    {
        $anterior = $this->principal();
        $nuevo = $this->administrador();

        $this->artisan('pol:transferir-principal', ['correo' => $nuevo->email])
            ->expectsOutputToContain("Administrador principal actual: {$anterior->email} (seguirá siendo administrador).")
            ->expectsQuestion("¿Transferir el rol de administrador principal a {$nuevo->email}? Escribe «sí» para confirmar o «no» para cancelar", 'sí')
            ->expectsOutputToContain("{$nuevo->email} es ahora el administrador principal. {$anterior->email} sigue siendo administrador.")
            ->assertSuccessful();

        $this->assertTrue($nuevo->fresh()->es_principal);
        $this->assertFalse($anterior->fresh()->es_principal);
        $this->assertSame('administrador', $anterior->fresh()->rol);
        $this->assertSame([$nuevo->id], User::query()->where('es_principal', true)->pluck('id')->all());

        // Log "seguridad" con quién lo ejecutó, y auditoría de ambos cambios con el ejecutor.
        $registro = $this->registros['seguridad.administrador_principal'][0] ?? [];
        $this->assertSame(['usuario_id' => $nuevo->id, 'principal_anterior_id' => $anterior->id, ...EjecutorConsola::identificar()],
            array_intersect_key($registro, array_flip(['usuario_id', 'principal_anterior_id', 'usuario_so', 'equipo'])));
        $this->assertNotSame('', $registro['usuario_so']);
        $this->assertNotSame('', $registro['equipo']);
        foreach ([$anterior, $nuevo] as $usuario) {
            $this->assertSame($this->descripcionEjecutor(), $this->ultimaAuditoria($usuario)->user_agent);
        }
    }

    public function test_sin_principal_el_traspaso_lo_designa(): void
    {
        $admin = $this->administrador();

        $this->artisan('pol:transferir-principal', ['correo' => $admin->email, '--force' => true])
            ->expectsOutputToContain('Todavía no hay ningún administrador principal.')
            ->expectsOutputToContain("{$admin->email} es ahora el administrador principal.")
            ->assertSuccessful();

        $this->assertTrue($admin->fresh()->es_principal);
        $this->assertNull($this->registros['seguridad.administrador_principal'][0]['principal_anterior_id'] ?? null);
    }

    public function test_el_traspaso_solo_acepta_un_administrador_activo_y_pide_confirmacion(): void
    {
        $principal = $this->principal();
        $ciudadano = User::factory()->create();
        $inactivo = $this->administrador(['estado' => false]);
        $admin = $this->administrador();

        $this->artisan('pol:transferir-principal', ['correo' => 'nadie@prueba.test', '--force' => true])->expectsOutputToContain('No existe ninguna cuenta con ese correo.')->assertFailed();
        foreach ([$ciudadano, $inactivo] as $usuario) {
            $this->artisan('pol:transferir-principal', ['correo' => $usuario->email, '--force' => true])
                ->expectsOutputToContain("{$usuario->email} debe ser un administrador con la cuenta activa; no se hizo ningún cambio.")
                ->assertFailed();
        }
        $this->artisan('pol:transferir-principal', ['correo' => $principal->email, '--force' => true])
            ->expectsOutputToContain('ya es el administrador principal; no se hizo ningún cambio.')
            ->assertSuccessful();
        $this->artisan('pol:transferir-principal', ['correo' => $admin->email])
            ->expectsQuestion("¿Transferir el rol de administrador principal a {$admin->email}? Escribe «sí» para confirmar o «no» para cancelar", 'no')
            ->expectsOutputToContain('Cancelado: no se hizo ningún cambio.')
            ->assertFailed();

        $this->assertSame([$principal->id], User::query()->where('es_principal', true)->pluck('id')->all());
        $this->assertArrayNotHasKey('seguridad.administrador_principal', $this->registros);
    }

    public function test_promover_y_retirar_registran_quien_los_ejecuto(): void
    {
        $this->principal();
        $ciudadano = User::factory()->create();

        $this->artisan('pol:promover-administrador', ['correo' => $ciudadano->email, '--force' => true])->assertSuccessful();
        $this->assertSame($this->descripcionEjecutor(), $this->ultimaAuditoria($ciudadano)->user_agent);

        $this->artisan('pol:retirar-administrador', ['correo' => $ciudadano->email, '--force' => true])->assertSuccessful();
        $this->assertSame($this->descripcionEjecutor(), $this->ultimaAuditoria($ciudadano)->user_agent);

        foreach ($this->registros['seguridad.rol_administrador'] as $registro) {
            $this->assertSame(EjecutorConsola::identificar(), array_intersect_key($registro, array_flip(['usuario_so', 'equipo'])));
        }
        $this->assertCount(2, $this->registros['seguridad.rol_administrador']);

        // Fuera de un comando de administradores la auditoría no lleva el ejecutor de consola.
        $ciudadano->forceFill(['telefono' => '71234567'])->save();
        $this->assertStringStartsNotWith('consola:', (string) $this->ultimaAuditoria($ciudadano)->user_agent);
    }

    private function administrador(array $atributos = []): User
    {
        $usuario = User::factory()->create($atributos);
        $usuario->forceFill(['rol' => 'administrador'])->save();

        return $usuario->fresh();
    }

    private function principal(): User
    {
        $principal = $this->administrador();
        $principal->forceFill(['es_principal' => true])->save();

        return $principal->fresh();
    }

    /** Ejecuta $operacion en un punto de guardado y devuelve el error de PostgreSQL (o null si no hubo). */
    private function errorAlGuardar(callable $operacion): ?QueryException
    {
        try {
            DB::transaction($operacion);
        } catch (QueryException $error) {
            return $error;
        }

        return null;
    }

    private function ultimaAuditoria(User $usuario): Auditoria
    {
        return Auditoria::query()->where('modelo', 'User')->where('modelo_id', $usuario->id)->where('accion', 'actualizado')->latest('id')->firstOrFail();
    }

    private function descripcionEjecutor(): string
    {
        ['usuario_so' => $usuario, 'equipo' => $equipo] = EjecutorConsola::identificar();

        return "consola: {$usuario}@{$equipo}";
    }
}
