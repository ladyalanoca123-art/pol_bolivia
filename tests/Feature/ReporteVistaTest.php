<?php

namespace Tests\Feature;

use App\Models\FotoMascota;
use App\Models\Incidencia;
use App\Models\Mascota;
use App\Models\PostulacionVoluntariado;
use App\Models\SeguimientoAdopcion;
use App\Models\SolicitudAdopcion;
use App\Models\User;
use App\Models\VisitaSeguimiento;
use App\Services\ReporteAdministrador;
use App\Support\PeriodoReporte;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CuestionarioAdopcionDatos;
use Tests\Support\CuestionarioVoluntariadoDatos;
use Tests\TestCase;

/**
 * Rediseño de Reportes: cálculos de gestión, embudo, evolución mensual y estado actual de las mascotas
 * (ReporteAdministrador) y la vista /admin/reportes. Hoy = 14/10/2026 en La Paz; período este_mes.
 */
class ReporteVistaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('pol_bolivia_test', DB::connection()->getDatabaseName());
        $this->travelTo(CarbonImmutable::parse('2026-10-14 10:00', PeriodoReporte::ZONA));
        $this->admin = User::factory()->create(['rol' => 'administrador']);
    }

    // ---------- Cálculos ----------

    public function test_indicadores_de_gestion_del_periodo_y_del_anterior(): void
    {
        $this->resuelta('aceptada', '2026-10-01 00:00:00-04', '2026-10-03 00:00:00-04');   // 2 días
        $this->resuelta('rechazada', '2026-10-02 00:00:00-04', '2026-10-06 00:00:00-04');  // 4 días
        $this->resuelta('aceptada', '2026-09-09 00:00:00-04', '2026-09-10 00:00:00-04');   // anterior, 1 día
        $this->resuelta('aceptada', '2026-10-01 00:00:00-04', '2026-10-15 00:00:00-04');   // fuera

        [$seguimiento, $voluntario] = $this->seguimientoActivo();
        $incidencia = Incidencia::create(['seguimiento_id' => $seguimiento->id, 'reportado_por' => $voluntario->id, 'tipo' => 'salud', 'descripcion' => 'x', 'estado' => 'pendiente']);
        $incidencia->update(['estado' => 'atendida', 'comentarios_resolucion' => 'Resuelta', 'atendida_por' => $this->admin->id, 'atendida_at' => now()]);
        DB::table('incidencias')->where('id', $incidencia->id)->update(['created_at' => '2026-10-01 00:00:00-04', 'atendida_at' => '2026-10-02 12:00:00-04']);
        $this->visita($seguimiento, $voluntario, '2026-10-05', 'realizada');
        $this->visita($seguimiento, $voluntario, '2026-10-06', 'no_asistio');
        [$otro, $otroVoluntario] = $this->seguimientoActivo();
        $this->visita($otro, $otroVoluntario, '2026-10-07', 'programada');   // sin resultado: no cuenta
        $this->visita($otro, $otroVoluntario, '2026-10-15', 'realizada');    // fuera del período

        $postulacion = PostulacionVoluntariado::create(['usuario_id' => User::factory()->create(['rol' => 'ciudadano'])->id, 'estado' => 'pendiente', ...CuestionarioVoluntariadoDatos::registro()]);
        DB::table('postulaciones_voluntariado')->where('id', $postulacion->id)->update(['created_at' => '2026-10-01 00:00:00-04', 'estado' => 'rechazada', 'revisado_por' => $this->admin->id, 'revisado_at' => '2026-10-10 00:00:00-04']);

        $g = app(ReporteAdministrador::class)->gestion(PeriodoReporte::crear('este_mes'));

        $this->assertSame(['actual' => 50.0, 'anterior' => 100.0, 'aceptadas' => 1, 'resueltas' => 2], $g['tasa_aprobacion']);
        $this->assertSame(['actual' => 3.0, 'anterior' => 1.0, 'diferencia' => 2.0], $g['tiempo_respuesta']);
        $this->assertSame(['actual' => 2.0, 'anterior' => 1.0, 'diferencia' => 1.0], $g['dias_hasta_adopcion']);
        $this->assertSame(1.5, $g['atencion_incidencias']['actual']);
        $this->assertNull($g['atencion_incidencias']['anterior']);
        $this->assertSame(9.0, $g['revision_postulaciones']['actual']);
        $this->assertSame(['actual' => 50.0, 'anterior' => null, 'realizadas' => 1, 'con_resultado' => 2], $g['visitas_cumplidas']);
    }

    public function test_embudo_de_las_solicitudes_recibidas_en_el_periodo(): void
    {
        foreach (['pendiente', 'en_espera', 'rechazada', 'cancelada'] as $estado) {
            $this->resuelta($estado, '2026-10-05 10:00:00-04');
        }
        $aceptada = $this->resuelta('aceptada', '2026-10-05 10:00:00-04');
        $seguimiento = $aceptada->seguimiento()->firstOrFail();
        $voluntario = User::factory()->create(['rol' => 'voluntario']);
        $seguimiento->update(['voluntario_id' => $voluntario->id, 'estado' => 'activo', 'fecha_inicio' => now()]);
        $seguimiento->update(['estado' => 'cerrado', 'fecha_cierre' => now(), 'observaciones_cierre' => 'Cierre']);
        $this->resuelta('aceptada', '2026-09-20 10:00:00-04');     // recibida fuera del período

        $embudo = app(ReporteAdministrador::class)->embudo(PeriodoReporte::crear('este_mes'));

        $this->assertSame(['recibidas' => 5, 'evaluadas' => 3, 'aceptadas' => 1, 'seguimiento_cerrado' => 1, 'porcentaje_adopcion' => 20.0], $embudo);
    }

    public function test_evolucion_de_los_ultimos_seis_meses_en_la_paz(): void
    {
        $this->resuelta('pendiente', '2026-05-01 00:00:00-04');           // primer mes de la serie
        $this->resuelta('pendiente', '2026-05-01 03:59:59+00');           // 30/04 en La Paz: fuera
        $this->resuelta('pendiente', '2026-10-14 23:59:59.999999-04');    // mes en curso
        $this->resuelta('aceptada', '2026-08-01 10:00:00-04', '2026-09-30 23:59:59.999999-04');

        $meses = app(ReporteAdministrador::class)->evolucionMensual();

        $this->assertSame(['2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10'], array_column($meses, 'mes'));
        $this->assertSame([1, 0, 0, 1, 0, 1], array_column($meses, 'solicitudes'));
        $this->assertSame([0, 0, 0, 0, 1, 0], array_column($meses, 'adopciones'));
        $this->assertSame([false, false, false, false, false, true], array_column($meses, 'en_curso'));
        $this->assertSame('oct', end($meses)['etiqueta']);
    }

    public function test_mascotas_disponibles_y_las_que_mas_esperan(): void
    {
        $bruno = $this->mascota(['nombre' => 'Bruno', 'tamano' => 'grande'], '2026-05-25 10:00:00-04');
        $nina = $this->mascota(['nombre' => 'Nina', 'especie' => 'gato'], '2026-06-18 10:00:00-04');
        $this->mascota(['nombre' => 'Reciente'], '2026-10-14 08:00:00-04');
        $adoptada = $this->resuelta('aceptada', '2026-10-01 10:00:00-04');   // su mascota pasa a adoptada
        DB::table('mascotas')->where('id', $adoptada->mascota_id)->update(['created_at' => '2026-01-01 10:00:00-04']);

        $reportes = app(ReporteAdministrador::class);

        $this->assertSame(['total' => 3, 'especie' => ['perro' => 2, 'gato' => 1], 'tamano' => ['pequeno' => 0, 'mediano' => 2, 'grande' => 1]], $reportes->mascotasDisponibles());
        $espera = $reportes->esperanMasTiempo();
        $this->assertSame([$bruno->id, $nina->id], $espera->take(2)->map(fn ($e) => $e['mascota']->id)->all());
        $this->assertSame([142, 118, 0], $espera->pluck('dias')->all());
    }

    // ---------- Vista ----------

    public function test_sin_periodo_muestra_totales_y_estado_actual_y_pide_elegir_un_periodo(): void
    {
        $html = $this->vista()->assertSee('Totales acumulados y estado actual')->assertSee('Elige un período para ver qué pasó con sus solicitudes.')
            ->assertSee('Estado actual')->assertSee('Total mascotas')->assertSee('Postulaciones canceladas')
            ->assertDontSee('Adopciones en el período')->assertDontSee('aria-current="true"', false)->getContent();

        // Solo el contenido de la vista: el menú de usuario del layout (x-dropdown) es ajeno a Reportes.
        $contenido = substr($html, strpos($html, '<main>'));
        $this->assertStringNotContainsString(' style="', $contenido, 'la vista no debe usar atributos style');
        $this->assertStringNotContainsString('<script', $contenido, 'la vista no necesita scripts');
    }

    public function test_con_periodo_marca_el_seleccionado_y_muestra_la_comparacion(): void
    {
        $this->resuelta('aceptada', '2026-10-02 10:00:00-04', '2026-10-03 10:00:00-04');
        $this->resuelta('aceptada', '2026-09-02 10:00:00-04', '2026-09-03 10:00:00-04');

        $this->vista(['periodo' => 'este_mes'])
            ->assertSee('1 al 14 de octubre de 2026 · comparado con el período anterior (1 al 14 de septiembre de 2026)')
            ->assertSeeInOrder(['aria-current="true"', 'Este mes'], false)
            ->assertSee('Igual que el período anterior (1)')
            ->assertSee('Adopciones en el período: 1')
            ->assertSee('1 aceptada de 1 resuelta')
            ->assertSee('Quitar período');
    }

    public function test_el_periodo_personalizado_conserva_las_fechas_y_muestra_los_errores(): void
    {
        $html = $this->vista(['periodo' => 'personalizado', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-09-30'])
            ->assertSee('value="2026-09-01"', false)->assertSee('value="2026-09-30"', false)->getContent();
        $this->assertMatchesRegularExpression('/<details class="group open:basis-full"\s+open/', $html, 'el formulario personalizado queda abierto');

        $this->actingAs($this->admin)
            ->from(route('admin.reportes.index'))
            ->get(route('admin.reportes.index', ['periodo' => 'personalizado', 'fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-01']))
            ->assertRedirect(route('admin.reportes.index'));
        $this->actingAs($this->admin)->get(route('admin.reportes.index'))
            ->assertSee('La fecha inicial no puede ser posterior a la fecha final.')
            ->assertSee('value="2026-10-10"', false);
    }

    public function test_estados_vacios_sin_datos(): void
    {
        $this->vista(['periodo' => 'este_mes'])
            ->assertSee('No se recibieron solicitudes en este período.')
            ->assertSee('No hay solicitudes ni adopciones registradas en los últimos 6 meses.')
            ->assertSee('No hay mascotas disponibles en este momento.')
            ->assertSee('Sin decisiones')
            ->assertSee('Sin casos');
    }

    public function test_los_datos_dinamicos_se_escapan(): void
    {
        $this->mascota(['nombre' => '<b>Toby</b>'], '2026-09-01 10:00:00-04');

        $this->vista()->assertSee('&lt;b&gt;Toby&lt;/b&gt;', false)->assertDontSee('<b>Toby</b>', false);
    }

    // ---------- Datos de prueba ----------

    private function vista(array $parametros = [])
    {
        return $this->actingAs($this->admin)->get(route('admin.reportes.index', $parametros))->assertOk();
    }

    private function mascota(array $atributos = [], ?string $creada = null): Mascota
    {
        $mascota = DB::transaction(function () use ($atributos): Mascota {
            $mascota = Mascota::create([...['nombre' => fake()->unique()->firstName(), 'especie' => 'perro', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $this->admin->id], ...$atributos]);
            FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => 'mascotas/v.jpg', 'es_principal' => true, 'orden' => 1]);

            return $mascota;
        });
        if ($creada) {
            DB::table('mascotas')->where('id', $mascota->id)->update(['created_at' => $creada]);
        }

        return $mascota;
    }

    /** Solicitud en el estado indicado (transición real) con las fechas fijadas en la base de pruebas. */
    private function resuelta(string $estado, string $creada, ?string $resuelta = null): SolicitudAdopcion
    {
        $solicitud = SolicitudAdopcion::create([
            ...CuestionarioAdopcionDatos::registro(),
            'solicitante_id' => User::factory()->create(['rol' => 'ciudadano'])->id,
            'mascota_id' => $this->mascota()->id,
            'estado' => 'pendiente',
        ]);
        if ($estado !== 'pendiente') {
            DB::table('solicitudes_adopcion')->where('id', $solicitud->id)->update(['estado' => $estado]);
        }
        DB::table('solicitudes_adopcion')->where('id', $solicitud->id)->update(array_filter(['created_at' => $creada, 'resuelta_at' => $resuelta]));

        return $solicitud;
    }

    /** @return array{0: SeguimientoAdopcion, 1: User} */
    private function seguimientoActivo(): array
    {
        $seguimiento = $this->resuelta('aceptada', '2026-08-01 10:00:00-04', '2026-08-02 10:00:00-04')->seguimiento()->firstOrFail();
        $voluntario = User::factory()->create(['rol' => 'voluntario']);
        $seguimiento->update(['voluntario_id' => $voluntario->id, 'estado' => 'activo', 'fecha_inicio' => now()]);

        return [$seguimiento, $voluntario];
    }

    private function visita(SeguimientoAdopcion $seguimiento, User $voluntario, string $fecha, string $estado): void
    {
        VisitaSeguimiento::create(['seguimiento_id' => $seguimiento->id, 'voluntario_id' => $voluntario->id, 'fecha_programada' => $fecha, 'estado' => $estado, 'fecha_realizada' => $estado === 'realizada' ? "{$fecha} 16:00:00-04" : null]);
    }
}
