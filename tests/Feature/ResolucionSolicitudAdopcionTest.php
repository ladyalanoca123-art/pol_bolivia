<?php

namespace Tests\Feature;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\SeguimientoAdopcion;
use App\Models\SolicitudAdopcion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CuestionarioAdopcionDatos;
use Tests\TestCase;

/**
 * Fecha real de resolución (solicitudes_adopcion.resuelta_at): trigger de PostgreSQL y recuperación de
 * las fechas históricas (migración 2026_10_14_000000_add_resuelta_at_to_solicitudes_adopcion).
 */
class ResolucionSolicitudAdopcionTest extends TestCase
{
    use RefreshDatabase;

    private const MOTIVO = ['comentarios_admin' => 'Motivo de la decisión de prueba'];

    private const MIGRACION = 'migrations/2026_10_14_000000_add_resuelta_at_to_solicitudes_adopcion.php';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('pol_bolivia_test', DB::connection()->getDatabaseName());
        $this->admin = User::factory()->create(['rol' => 'administrador']);
    }

    // ---------- Transiciones (trigger) ----------

    public function test_al_aceptar_se_registra_la_fecha_de_resolucion_sin_alterar_los_efectos_de_la_aceptacion(): void
    {
        $solicitud = $this->solicitud();
        $this->decidir('evaluate', $solicitud);
        $this->assertNull($this->resueltaAt($solicitud), 'en evaluación sigue abierta');

        $this->decidir('accept', $solicitud);

        $this->assertNotNull($this->resueltaAt($solicitud));
        // Misma fuente que updated_at: CURRENT_TIMESTAMP de la transacción de la transición.
        $this->assertSame($this->texto($solicitud, 'updated_at'), $this->texto($solicitud, 'resuelta_at'));
        // Efectos existentes de la aceptación intactos.
        $this->assertSame('adoptada', $solicitud->mascota->fresh()->estado);
        $this->assertSame('sin_asignar', SeguimientoAdopcion::query()->where('solicitud_id', $solicitud->id)->sole()->estado);
        $this->assertInstanceOf(\DateTimeInterface::class, $solicitud->fresh()->resuelta_at);
    }

    public function test_al_rechazar_se_registra_la_fecha_de_resolucion(): void
    {
        $solicitud = $this->solicitud();

        $this->decidir('reject', $solicitud);

        $this->assertSame('rechazada', $solicitud->fresh()->estado);
        $this->assertNotNull($this->resueltaAt($solicitud));
    }

    public function test_al_pasar_a_cancelada_se_registra_la_fecha_de_resolucion(): void
    {
        // La aplicación no tiene ningún flujo de cancelación (ni controlador ni función de PostgreSQL).
        // Se comprueba el trigger con un UPDATE directo, que las reglas actuales de la base admiten.
        $solicitud = $this->solicitud();

        DB::table('solicitudes_adopcion')->where('id', $solicitud->id)->update(['estado' => 'cancelada']);

        $this->assertNotNull($this->resueltaAt($solicitud));
    }

    public function test_los_estados_abiertos_no_tienen_fecha_y_una_solicitud_resuelta_no_puede_reabrirse(): void
    {
        $solicitud = $this->solicitud();
        $this->assertNull($this->resueltaAt($solicitud), 'una solicitud nueva nace sin resolver');

        $this->decidir('evaluate', $solicitud);
        $this->assertNull($this->resueltaAt($solicitud));

        // Volver a un estado abierto no es un flujo permitido: validar_cambio_estado_solicitud lo
        // rechaza, y la fecha no cambia. Si alguna regla futura lo permitiera, el trigger la limpiaría.
        $this->decidir('reject', $solicitud);
        $resuelta = $this->texto($solicitud, 'resuelta_at');
        try {
            DB::transaction(fn () => DB::table('solicitudes_adopcion')->where('id', $solicitud->id)->update(['estado' => 'pendiente']));
            $this->fail('PostgreSQL debía rechazar la reapertura.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('estado final no puede cambiar', $e->getMessage());
        }
        $this->assertSame($resuelta, $this->texto($solicitud, 'resuelta_at'));
    }

    public function test_una_nueva_solicitud_no_puede_nacer_resuelta(): void
    {
        $id = DB::table('solicitudes_adopcion')->insertGetId([
            'solicitante_id' => User::factory()->create(['rol' => 'ciudadano'])->id,
            'mascota_id' => $this->mascota()->id,
            'estado' => 'pendiente',
            'resuelta_at' => '2026-01-01 00:00:00+00',
            ...$this->registroParaInsertar(),
        ]);

        $this->assertNull(DB::table('solicitudes_adopcion')->where('id', $id)->value('resuelta_at'));
    }

    public function test_una_operacion_que_no_es_una_transicion_no_sobrescribe_la_fecha(): void
    {
        $solicitud = $this->solicitud();
        $this->decidir('reject', $solicitud);
        // Fecha histórica conocida (sin tocar estado: el trigger no interviene).
        DB::table('solicitudes_adopcion')->where('id', $solicitud->id)->update(['resuelta_at' => '2026-09-01 10:00:00+00']);

        // Reescribir el mismo estado (estado aparece en el UPDATE, pero no cambia).
        DB::table('solicitudes_adopcion')->where('id', $solicitud->id)->update(['estado' => 'rechazada']);
        // Editar otro campo con Eloquent.
        $solicitud->fresh()->forceFill(['comentarios_admin' => 'Comentario posterior a la resolución'])->save();

        $this->assertSame('2026-09-01 10:00:00.000000', $this->textoUtc($solicitud, 'resuelta_at'));
    }

    // ---------- Recuperación de fechas históricas (migración) ----------

    public function test_la_migracion_recupera_la_fecha_desde_la_auditoria_de_la_transicion(): void
    {
        // La historia debe ser coherente: la creación (auditoría «creado») precede a las decisiones.
        $this->travelTo(CarbonImmutable::parse('2026-09-30 18:00:00', 'America/La_Paz'));
        $solicitud = $this->solicitud();
        $this->travelTo(CarbonImmutable::parse('2026-10-01 09:15:00', 'America/La_Paz'));
        $this->decidir('evaluate', $solicitud);
        $this->travelTo(CarbonImmutable::parse('2026-10-03 16:40:00', 'America/La_Paz'));
        $this->decidir('accept', $solicitud);
        $this->travelBack();
        $this->olvidarResolucion($solicitud);

        $resultado = $this->migracion()->recuperarFechasDeResolucion();

        // La auditoría de la aceptación (16:40 en La Paz, 20:40 UTC), no la de la evaluación.
        $this->assertSame('2026-10-03 20:40:00.000000', $this->textoUtc($solicitud, 'resuelta_at'));
        $this->assertSame(['auditoria' => 1, 'cierre_por_aceptacion' => 0, 'updated_at' => 0, 'sin_fecha' => 0], $resultado);
    }

    public function test_con_varias_auditorias_elige_la_que_dejo_la_solicitud_en_su_estado_final(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-01 08:00:00', 'UTC')); // creación anterior a la historia
        $solicitud = $this->solicitud();
        $this->travelBack();
        DB::table('solicitudes_adopcion')->where('id', $solicitud->id)->update(['estado' => 'rechazada']);
        $this->olvidarResolucion($solicitud);

        // Secuencia histórica anómala registrada en auditorias (en UTC, sin zona).
        $this->auditoria($solicitud->id, 'pendiente', 'rechazada', '2026-09-10 12:00:00');
        $this->auditoria($solicitud->id, 'rechazada', 'en_espera', '2026-09-11 12:00:00');
        $this->auditoria($solicitud->id, 'en_espera', 'rechazada', '2026-09-12 12:00:00'); // la transición final
        $this->auditoria($solicitud->id, 'rechazada', 'rechazada', '2026-09-13 12:00:00'); // no es una transición
        // Ruido: otro modelo con el mismo id y otra solicitud.
        $this->auditoria($solicitud->id, 'pendiente', 'rechazada', '2026-09-14 12:00:00', 'PostulacionVoluntariado');
        $this->auditoria($solicitud->id + 1000, 'en_espera', 'rechazada', '2026-09-15 12:00:00');

        $this->migracion()->recuperarFechasDeResolucion();

        $this->assertSame('2026-09-12 12:00:00.000000', $this->textoUtc($solicitud, 'resuelta_at'));
    }

    public function test_sin_auditoria_valida_usa_updated_at_sin_modificarlo(): void
    {
        $solicitud = $this->solicitud();
        DB::table('solicitudes_adopcion')->where('id', $solicitud->id)->update(['estado' => 'cancelada']); // sin auditoría
        $this->olvidarResolucion($solicitud);
        $updatedAt = $this->texto($solicitud, 'updated_at');

        $resultado = $this->migracion()->recuperarFechasDeResolucion();

        $this->assertSame($updatedAt, $this->texto($solicitud, 'resuelta_at'));
        $this->assertSame($updatedAt, $this->texto($solicitud, 'updated_at'), 'la recuperación no debe cambiar updated_at');
        $this->assertSame(['auditoria' => 0, 'cierre_por_aceptacion' => 0, 'updated_at' => 1, 'sin_fecha' => 0], $resultado);
        $this->assertSame('O', $this->estadoTriggerUpdatedAt());
    }

    public function test_sin_fecha_fiable_conserva_null(): void
    {
        $solicitud = $this->solicitud();
        DB::table('solicitudes_adopcion')->where('id', $solicitud->id)->update(['estado' => 'cancelada']);
        $this->olvidarResolucion($solicitud);
        // updated_at anterior a created_at: no es una fecha fiable de resolución.
        DB::table('solicitudes_adopcion')->where('id', $solicitud->id)->update(['created_at' => now()->addDay()]);

        $resultado = $this->migracion()->recuperarFechasDeResolucion();

        $this->assertNull($this->resueltaAt($solicitud));
        $this->assertSame(1, $resultado['sin_fecha']);
    }

    public function test_la_migracion_respeta_las_fechas_ya_registradas_y_las_solicitudes_abiertas(): void
    {
        $resuelta = $this->solicitud();
        $this->travelTo(CarbonImmutable::parse('2026-10-02 10:00:00', 'America/La_Paz'));
        $this->decidir('reject', $resuelta);
        $this->travelBack();
        DB::table('solicitudes_adopcion')->where('id', $resuelta->id)->update(['resuelta_at' => '2026-08-01 08:00:00+00']);
        $abierta = $this->solicitud();

        $this->migracion()->recuperarFechasDeResolucion();

        $this->assertSame('2026-08-01 08:00:00.000000', $this->textoUtc($resuelta, 'resuelta_at'));
        $this->assertNull($this->resueltaAt($abierta));
    }

    // ---------- Cierre automático (cerrada) ----------

    public function test_al_aceptar_las_demas_solicitudes_activas_se_cierran_con_la_fecha_de_esa_aceptacion(): void
    {
        $mascota = $this->mascota();
        $aceptada = $this->solicitud($mascota);
        $pendiente = $this->solicitud($mascota);
        $enEvaluacion = $this->solicitud($mascota);
        $this->decidir('evaluate', $enEvaluacion);
        $otraMascota = $this->solicitud();

        $this->decidir('evaluate', $aceptada);
        $this->decidir('accept', $aceptada);

        foreach ([$pendiente, $enEvaluacion] as $cerrada) {
            $this->assertSame('cerrada', $cerrada->fresh()->estado);
            // Misma transacción que la aceptación: mismo CURRENT_TIMESTAMP.
            $this->assertSame($this->texto($aceptada, 'resuelta_at'), $this->texto($cerrada, 'resuelta_at'));
        }
        $this->assertNull($this->resueltaAt($otraMascota), 'las solicitudes de otras mascotas no se cierran');
    }

    public function test_las_solicitudes_cerradas_no_cuentan_como_adopciones(): void
    {
        $mascota = $this->mascota();
        $aceptada = $this->solicitud($mascota);
        $this->solicitud($mascota);
        $this->solicitud($mascota);
        $this->decidir('evaluate', $aceptada);
        $this->decidir('accept', $aceptada);

        // Con fecha de resolución hay tres solicitudes, pero adopción realizada solo una (estado = aceptada).
        $this->assertSame(3, SolicitudAdopcion::query()->whereNotNull('resuelta_at')->count());
        $this->assertSame(2, SolicitudAdopcion::query()->where('estado', 'cerrada')->whereNotNull('resuelta_at')->count());
        $this->assertSame([$aceptada->id], SolicitudAdopcion::query()->where('estado', 'aceptada')->whereNotNull('resuelta_at')->pluck('id')->all());
    }

    public function test_la_migracion_recupera_el_cierre_desde_la_aceptacion_de_la_misma_mascota(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 08:00:00', 'America/La_Paz'));
        $mascota = $this->mascota();
        $aceptada = $this->solicitud($mascota);
        $cerrada = $this->solicitud($mascota);
        $this->decidir('evaluate', $aceptada);
        $this->travelTo(CarbonImmutable::parse('2026-10-02 11:30:00', 'America/La_Paz'));
        $this->decidir('accept', $aceptada);
        $this->travelBack();
        $this->olvidarResolucion($aceptada);
        $this->olvidarResolucion($cerrada);

        $resultado = $this->migracion()->recuperarFechasDeResolucion();

        // La aceptada desde su auditoría; la cerrada (sin auditoría propia) desde esa aceptación.
        $this->assertSame('2026-10-02 15:30:00.000000', $this->textoUtc($aceptada, 'resuelta_at'));
        $this->assertSame('2026-10-02 15:30:00.000000', $this->textoUtc($cerrada, 'resuelta_at'));
        $this->assertSame(['auditoria' => 1, 'cierre_por_aceptacion' => 1, 'updated_at' => 0, 'sin_fecha' => 0], $resultado);
    }

    public function test_un_cierre_posterior_a_su_creacion_no_coherente_no_toma_la_fecha_de_la_aceptacion(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 08:00:00', 'America/La_Paz'));
        $mascota = $this->mascota();
        $aceptada = $this->solicitud($mascota);
        $cerrada = $this->solicitud($mascota);
        $this->decidir('evaluate', $aceptada);
        $this->decidir('accept', $aceptada);
        $this->travelBack();
        $this->olvidarResolucion($aceptada);
        $this->olvidarResolucion($cerrada);
        // created_at posterior a la aceptación (y a updated_at): ninguna fecha es coherente.
        DB::table('solicitudes_adopcion')->where('id', $cerrada->id)->update(['created_at' => now()->addDay()]);

        $resultado = $this->migracion()->recuperarFechasDeResolucion();

        $this->assertNotNull($this->resueltaAt($aceptada));
        $this->assertNull($this->resueltaAt($cerrada));
        $this->assertSame(['auditoria' => 1, 'cierre_por_aceptacion' => 0, 'updated_at' => 0, 'sin_fecha' => 1], $resultado);
    }

    public function test_sin_fecha_auditada_de_la_aceptacion_el_cierre_usa_su_propio_updated_at(): void
    {
        $mascota = $this->mascota();
        $aceptada = $this->solicitud($mascota);
        $cerrada = $this->solicitud($mascota);
        // Aceptación sin auditoría (UPDATE directo): PostgreSQL cierra igualmente la otra solicitud.
        DB::table('solicitudes_adopcion')->where('id', $aceptada->id)->update(['estado' => 'aceptada']);
        $this->assertSame('cerrada', $cerrada->fresh()->estado);
        $this->olvidarResolucion($aceptada);
        $this->olvidarResolucion($cerrada);

        $resultado = $this->migracion()->recuperarFechasDeResolucion();

        $this->assertSame($this->texto($cerrada, 'updated_at'), $this->texto($cerrada, 'resuelta_at'));
        $this->assertSame(['auditoria' => 0, 'cierre_por_aceptacion' => 0, 'updated_at' => 2, 'sin_fecha' => 0], $resultado);
    }

    public function test_down_elimina_solo_lo_creado_por_la_migracion_y_up_lo_restituye(): void
    {
        $solicitud = $this->solicitud();
        $migracion = $this->migracion();

        $migracion->down();
        $this->assertFalse($this->existeColumna());
        $this->assertSame(0, (int) DB::selectOne("SELECT count(*) n FROM pg_trigger WHERE tgname = 'trigger_registrar_resolucion_solicitud'")->n);
        $this->assertSame(0, (int) DB::selectOne("SELECT count(*) n FROM pg_indexes WHERE indexname = 'idx_solicitudes_estado_resuelta_at'")->n);
        $this->assertSame(0, (int) DB::selectOne("SELECT count(*) n FROM pg_proc WHERE proname = 'registrar_resolucion_solicitud'")->n);
        $this->assertDatabaseHas('solicitudes_adopcion', ['id' => $solicitud->id, 'estado' => 'pendiente']);
        $this->assertSame(1, (int) DB::selectOne("SELECT count(*) n FROM pg_trigger WHERE tgname = 'trigger_validar_cambio_estado_solicitud'")->n);

        $migracion->up();
        $this->assertTrue($this->existeColumna());
        $this->assertSame('O', $this->estadoTriggerUpdatedAt());
    }

    // ---------- Datos de prueba ----------

    private function migracion(): object
    {
        return require database_path(self::MIGRACION);
    }

    private function decidir(string $accion, SolicitudAdopcion $solicitud): void
    {
        $this->actingAs($this->admin)
            ->patch(route("admin.solicitudes-adopcion.{$accion}", $solicitud), self::MOTIVO)
            ->assertRedirect(route('admin.solicitudes-adopcion.show', $solicitud));
    }

    private function solicitud(?Mascota $mascota = null): SolicitudAdopcion
    {
        return SolicitudAdopcion::create([
            ...CuestionarioAdopcionDatos::registro(),
            'solicitante_id' => User::factory()->create(['rol' => 'ciudadano'])->id,
            'mascota_id' => ($mascota ?? $this->mascota())->id,
            'estado' => 'pendiente',
        ]);
    }

    /** Atributos del cuestionario listos para DB::table()->insert() (JSON codificado). */
    private function registroParaInsertar(): array
    {
        return collect(CuestionarioAdopcionDatos::registro())
            ->map(fn ($valor) => is_array($valor) ? json_encode($valor) : $valor)
            ->all();
    }

    private function mascota(): Mascota
    {
        return DB::transaction(function (): Mascota {
            $mascota = Mascota::create(['nombre' => fake()->unique()->firstName(), 'especie' => 'perro', 'edad' => 3, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $this->admin->id]);
            FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => 'mascotas/r.jpg', 'es_principal' => true, 'orden' => 1]);

            return $mascota;
        });
    }

    /** Simula el estado previo a la migración (sin fecha), sin tocar estado: el trigger no interviene. */
    private function olvidarResolucion(SolicitudAdopcion $solicitud): void
    {
        DB::table('solicitudes_adopcion')->where('id', $solicitud->id)->update(['resuelta_at' => null]);
        $this->assertNull($this->resueltaAt($solicitud));
    }

    private function auditoria(int $modeloId, string $anterior, string $nuevo, string $utc, string $modelo = 'SolicitudAdopcion'): void
    {
        DB::table('auditorias')->insert([
            'accion' => 'actualizado', 'modelo' => $modelo, 'modelo_id' => $modeloId,
            'valores_anteriores' => json_encode(['estado' => $anterior]),
            'valores_nuevos' => json_encode(['estado' => $nuevo]),
            'created_at' => $utc,
        ]);
    }

    private function resueltaAt(SolicitudAdopcion $solicitud): ?string
    {
        return DB::table('solicitudes_adopcion')->where('id', $solicitud->id)->value('resuelta_at');
    }

    private function texto(SolicitudAdopcion $solicitud, string $columna): ?string
    {
        return DB::selectOne("SELECT {$columna}::text AS v FROM solicitudes_adopcion WHERE id = ?", [$solicitud->id])->v;
    }

    private function textoUtc(SolicitudAdopcion $solicitud, string $columna): ?string
    {
        return DB::selectOne("SELECT to_char({$columna} AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS.US') AS v FROM solicitudes_adopcion WHERE id = ?", [$solicitud->id])->v;
    }

    private function estadoTriggerUpdatedAt(): ?string
    {
        return DB::selectOne("SELECT tgenabled AS e FROM pg_trigger WHERE tgname = 'trigger_solicitudes_updated_at'")?->e;
    }

    private function existeColumna(): bool
    {
        return DB::selectOne("SELECT count(*) AS n FROM information_schema.columns WHERE table_name = 'solicitudes_adopcion' AND column_name = 'resuelta_at'")->n > 0;
    }
}
