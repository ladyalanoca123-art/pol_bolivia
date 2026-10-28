<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\User;
use App\Support\PresentadorAuditoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pantalla «Auditoría» en lenguaje claro: resumen, filtros, línea de tiempo por día y detalle de cambios sin JSON.
 */
class AuditoriaPantallaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // La auditoría no se puede borrar (trigger proteger_auditoria): las cuentas de prueba se crean sin registrarla,
        // para que la pantalla muestre solo los registros de cada prueba.
        $this->admin = User::withoutEvents(fn () => User::factory()->create(['rol' => 'administrador', 'nombres' => 'Lady', 'apellidos' => 'Alanoca']));
        $this->assertSame(0, Auditoria::count());
    }

    public function test_muestra_frases_claras_modulos_legibles_y_el_cambio_de_estado(): void
    {
        $this->registro(['usuario_id' => $this->admin->id, 'accion' => 'actualizado', 'modelo' => 'SolicitudAdopcion', 'modelo_id' => 12,
            'valores_anteriores' => ['estado' => 'en_espera'], 'valores_nuevos' => ['estado' => 'aprobada', 'comentarios_admin' => 'Hablé por WhatsApp.']]);

        $html = $this->actingAs($this->admin)->get(route('admin.auditoria.index'))->assertOk()
            ->assertSee('Auditoría')
            ->assertSee('Lady Alanoca')
            ->assertSee('actualizó una solicitud de adopción')
            ->assertSee('Solicitudes de adopción')
            ->assertSee('En evaluación')
            ->assertSee('Reservada')
            ->assertSee('Comentario del equipo')
            ->assertSee('Hablé por WhatsApp.')
            ->assertSee(route('admin.solicitudes-adopcion.show', 12), false)
            ->getContent();

        // Sin nombres técnicos ni JSON crudo.
        $this->assertStringNotContainsString('SolicitudAdopcion #12', $html);
        $this->assertStringNotContainsString('&quot;en_espera&quot;', $html);
        $this->assertStringNotContainsString('comentarios_admin', $html);
    }

    public function test_el_sistema_y_los_valores_se_presentan_en_espanol(): void
    {
        $this->registro(['usuario_id' => null, 'accion' => 'creado', 'modelo' => 'Mascota', 'modelo_id' => 3,
            'valores_nuevos' => ['nombre' => 'León', 'estado' => 'disponible', 'raza' => null]]);
        $this->registro(['usuario_id' => $this->admin->id, 'accion' => 'actualizado', 'modelo' => 'User', 'modelo_id' => 9,
            'valores_anteriores' => ['estado' => true], 'valores_nuevos' => ['estado' => false]]);

        $this->actingAs($this->admin)->get(route('admin.auditoria.index'))->assertOk()
            ->assertSee('El sistema')
            ->assertSee('registró una mascota')
            ->assertSee('León')->assertSee('Disponible')
            ->assertDontSee('Raza')                                  // en una creación no se listan los campos vacíos
            ->assertSee('actualizó una cuenta de usuario')
            ->assertSeeInOrder(['Activa', 'Desactivada']);
    }

    public function test_un_registro_eliminado_no_enlaza_y_cuenta_en_el_resumen(): void
    {
        $this->registro(['usuario_id' => $this->admin->id, 'accion' => 'eliminado', 'modelo' => 'FotoMascota', 'modelo_id' => 5,
            'valores_anteriores' => ['url' => 'mascotas/5/foto.jpg', 'es_principal' => false]]);

        $respuesta = $this->actingAs($this->admin)->get(route('admin.auditoria.index'))->assertOk()
            ->assertSee('eliminó una foto de mascota')
            ->assertSee('Valor eliminado')
            ->assertSee('Eliminación');

        $this->assertSame(1, $respuesta->viewData('resumen')['eliminaciones']);
        $this->assertSame(1, $respuesta->viewData('resumen')['hoy']);
    }

    public function test_filtra_por_persona_y_por_fechas(): void
    {
        $otra = User::withoutEvents(fn () => User::factory()->create(['rol' => 'administrador', 'nombres' => 'Carlos', 'apellidos' => 'Rojas']));
        $this->registro(['usuario_id' => $this->admin->id, 'accion' => 'creado', 'modelo' => 'Mascota', 'modelo_id' => 1, 'valores_nuevos' => ['nombre' => 'Uno']]);
        $this->registro(['usuario_id' => $otra->id, 'accion' => 'creado', 'modelo' => 'Mascota', 'modelo_id' => 2, 'valores_nuevos' => ['nombre' => 'Dos']]);
        $this->registro(['usuario_id' => $otra->id, 'accion' => 'creado', 'modelo' => 'Mascota', 'modelo_id' => 3, 'valores_nuevos' => ['nombre' => 'Antigua']], now()->subDays(10));

        $porPersona = $this->actingAs($this->admin)->get(route('admin.auditoria.index', ['usuario' => $otra->id]))->assertOk();
        $this->assertSame(2, $porPersona->viewData('auditorias')->total());
        $porPersona->assertSee('Carlos Rojas')->assertSee('2 registros con estos filtros');

        $hoy = now()->toDateString();
        $porFecha = $this->actingAs($this->admin)->get(route('admin.auditoria.index', ['desde' => $hoy, 'hasta' => $hoy]))->assertOk();
        $this->assertSame(2, $porFecha->viewData('auditorias')->total());
        $porFecha->assertDontSee('Antigua');

        $this->actingAs($this->admin)->get(route('admin.auditoria.index', ['desde' => $hoy, 'hasta' => now()->subDay()->toDateString()]))
            ->assertSessionHasErrors('hasta');
    }

    public function test_agrupa_por_dia_y_muestra_un_estado_vacio_claro(): void
    {
        $this->actingAs($this->admin)->get(route('admin.auditoria.index'))->assertOk()
            ->assertSee('Todavía no hay movimientos registrados');

        $this->registro(['usuario_id' => $this->admin->id, 'accion' => 'creado', 'modelo' => 'Mascota', 'modelo_id' => 1, 'valores_nuevos' => ['nombre' => 'Hoy']]);
        $this->registro(['usuario_id' => $this->admin->id, 'accion' => 'creado', 'modelo' => 'Mascota', 'modelo_id' => 2, 'valores_nuevos' => ['nombre' => 'Ayer']], now()->subDay());

        $this->actingAs($this->admin)->get(route('admin.auditoria.index'))->assertOk()
            ->assertSeeInOrder(['>Hoy', '>Ayer'], false);

        $this->actingAs($this->admin)->get(route('admin.auditoria.index', ['modelo' => 'Incidencia']))->assertOk()
            ->assertSee('No hay movimientos con estos filtros')
            ->assertSee('Quitar filtros');
    }

    public function test_presentador_traduce_campos_y_valores(): void
    {
        $this->assertSame('Número de acta', PresentadorAuditoria::campo('numero_acta'));
        $this->assertSame('Fecha limite', PresentadorAuditoria::campo('fecha_limite'));
        $this->assertSame('Sí', PresentadorAuditoria::valor('Mascota', 'compromiso_firmado', true));
        $this->assertSame('—', PresentadorAuditoria::valor('Mascota', 'raza', null));
        $this->assertSame('Datos del formulario', PresentadorAuditoria::valor('SolicitudAdopcion', 'respuestas', ['a' => 1]));
        $this->assertSame('2 alertas', PresentadorAuditoria::valor('SolicitudAdopcion', 'alertas', [['x'], ['y']]));
        $this->assertSame('27/10/2026', PresentadorAuditoria::valor('VisitaSeguimiento', 'fecha_programada', '2026-10-27'));
        $this->assertSame('No asistió', PresentadorAuditoria::valor('VisitaSeguimiento', 'estado', 'no_asistio'));
        $this->assertSame('Pérdida de contacto', PresentadorAuditoria::valor('Incidencia', 'tipo', 'perdida_contacto'));
        $this->assertSame('Pequeño', PresentadorAuditoria::valor('Mascota', 'tamano', 'pequeno'));
        $this->assertSame('Voluntario', PresentadorAuditoria::valor('User', 'rol', 'voluntario'));
        $this->assertSame('Sin observaciones', PresentadorAuditoria::valor('SolicitudAdopcion', 'nivel_alerta', 'verde'));
    }

    /** Inserta un registro de auditoría con su instante en UTC, como lo guarda App\Models\Auditoria. */
    private function registro(array $datos, ?Carbon $cuando = null): void
    {
        DB::table('auditorias')->insert([
            ...$datos,
            'valores_anteriores' => isset($datos['valores_anteriores']) ? json_encode($datos['valores_anteriores']) : null,
            'valores_nuevos' => isset($datos['valores_nuevos']) ? json_encode($datos['valores_nuevos']) : null,
            'ip' => '127.0.0.1',
            'created_at' => ($cuando ?? now())->copy()->utc()->format('Y-m-d H:i:s'),
        ]);
    }
}
