<?php

namespace Tests\Feature;

use App\Models\FotoMascota;
use App\Models\Incidencia;
use App\Models\Mascota;
use App\Models\SeguimientoAdopcion;
use App\Models\SolicitudAdopcion;
use App\Models\User;
use App\Models\VisitaSeguimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CompletaFlujoAdopcion;
use Tests\Support\CuestionarioAdopcionDatos;
use Tests\TestCase;

/**
 * «Cerrar seguimiento» solo se ofrece cuando PostgreSQL lo permite; si falta algo, se dice qué, y nunca se
 * muestra la página de error.
 */
class CierreSeguimientoTest extends TestCase
{
    use CompletaFlujoAdopcion;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['rol' => 'administrador']);
    }

    public function test_con_una_incidencia_sin_atender_no_hay_boton_y_se_dice_que_falta(): void
    {
        [$seguimiento, $voluntario] = $this->seguimientoActivo();
        Incidencia::create(['seguimiento_id' => $seguimiento->id, 'reportado_por' => $voluntario->id, 'tipo' => 'salud', 'descripcion' => 'Herida leve', 'estado' => 'pendiente']);
        VisitaSeguimiento::create(['seguimiento_id' => $seguimiento->id, 'voluntario_id' => $voluntario->id, 'fecha_programada' => '2026-11-12', 'estado' => 'programada']);

        $this->assertSame(
            ['Atiende 1 incidencia pendiente.', 'Registra la visita programada del 12/11/2026 (realizada o «No asistió»).'],
            $seguimiento->pendientesParaCerrar()
        );

        $this->actingAs($this->admin)->get(route('admin.seguimientos.show', $seguimiento))->assertOk()
            ->assertSee('Para cerrar el seguimiento:')
            ->assertSee('Atiende 1 incidencia pendiente.')
            ->assertSee('Registra la visita programada del 12/11/2026')
            ->assertDontSee(route('admin.seguimientos.close', $seguimiento), false);
    }

    public function test_si_igual_se_intenta_cerrar_se_muestra_el_motivo_y_no_un_error(): void
    {
        [$seguimiento, $voluntario] = $this->seguimientoActivo();
        Incidencia::create(['seguimiento_id' => $seguimiento->id, 'reportado_por' => $voluntario->id, 'tipo' => 'otro', 'descripcion' => 'Consulta', 'estado' => 'en_proceso']);

        $this->actingAs($this->admin)
            ->from(route('admin.seguimientos.show', $seguimiento))
            ->patch(route('admin.seguimientos.close', $seguimiento))
            ->assertRedirect(route('admin.seguimientos.show', $seguimiento))
            ->assertSessionHasErrors(['cierre' => 'Todavía no se puede cerrar: Atiende 1 incidencia pendiente.']);

        $this->assertSame('activo', $seguimiento->refresh()->estado);
    }

    public function test_sin_pendientes_aparece_el_boton_y_se_cierra(): void
    {
        [$seguimiento, $voluntario] = $this->seguimientoActivo();
        Incidencia::create(['seguimiento_id' => $seguimiento->id, 'reportado_por' => $voluntario->id, 'tipo' => 'salud', 'descripcion' => 'Revisión', 'estado' => 'atendida',
            'comentarios_resolucion' => 'Resuelto', 'atendida_por' => $voluntario->id, 'atendida_at' => now()]);
        VisitaSeguimiento::create(['seguimiento_id' => $seguimiento->id, 'voluntario_id' => $voluntario->id, 'fecha_programada' => now()->toDateString(), 'estado' => 'no_asistio']);

        $this->assertSame([], $seguimiento->pendientesParaCerrar());
        $this->actingAs($this->admin)->get(route('admin.seguimientos.show', $seguimiento))->assertOk()
            ->assertDontSee('Para cerrar el seguimiento:')
            ->assertSee(route('admin.seguimientos.close', $seguimiento), false);

        $this->actingAs($this->admin)->patch(route('admin.seguimientos.close', $seguimiento))
            ->assertRedirect()->assertSessionHas('status', 'El seguimiento fue cerrado.');
        $this->assertSame('cerrado', $seguimiento->refresh()->estado);
    }

    /** @return array{0: SeguimientoAdopcion, 1: User} */
    private function seguimientoActivo(): array
    {
        $ciudadano = User::factory()->create(['rol' => 'ciudadano']);
        $mascota = DB::transaction(function (): Mascota {
            $mascota = Mascota::create(['nombre' => fake()->unique()->firstName(), 'especie' => 'perro', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $this->admin->id]);
            FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => 'test.jpg', 'es_principal' => true, 'orden' => 1]);

            return $mascota;
        });
        $solicitud = SolicitudAdopcion::create(['solicitante_id' => $ciudadano->id, 'mascota_id' => $mascota->id, 'estado' => 'pendiente', ...CuestionarioAdopcionDatos::registro()]);
        $this->completarAdopcionPorRutas($this->admin, $solicitud);
        $seguimiento = $solicitud->seguimiento()->firstOrFail();
        $voluntario = User::factory()->create(['rol' => 'voluntario']);
        $seguimiento->update(['voluntario_id' => $voluntario->id, 'estado' => 'activo', 'fecha_inicio' => now()]);

        return [$seguimiento->refresh(), $voluntario];
    }
}
