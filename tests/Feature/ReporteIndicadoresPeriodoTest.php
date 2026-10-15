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
 * Indicadores de Reportes por período (ReporteAdministrador) y su integración en /admin/reportes.
 * Período de las pruebas: este_mes con hoy = 14/10/2026 (1–14 oct); anterior: 1–14 sep.
 */
class ReporteIndicadoresPeriodoTest extends TestCase
{
    use RefreshDatabase;

    private const PRIMER_INSTANTE = '2026-10-01 00:00:00-04';

    private const ULTIMO_INSTANTE = '2026-10-14 23:59:59.999999-04';

    private const ANTES = '2026-09-30 23:59:59.999999-04';      // ni en el período ni en el anterior

    private const DESPUES = '2026-10-15 00:00:00-04';

    private const ANTERIOR = '2026-09-10 12:00:00-04';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('pol_bolivia_test', DB::connection()->getDatabaseName());
        $this->travelTo(CarbonImmutable::parse('2026-10-14 10:00', PeriodoReporte::ZONA));
        $this->admin = User::factory()->create(['rol' => 'administrador']);
    }

    public function test_solicitudes_recibidas_por_fecha_de_creacion_con_limites_diarios(): void
    {
        foreach ([self::PRIMER_INSTANTE, self::ULTIMO_INSTANTE, self::ANTES, self::DESPUES, self::ANTERIOR] as $instante) {
            $this->fechas($this->solicitud(), ['created_at' => $instante]);
        }

        $indicador = $this->indicadores()['solicitudes_recibidas'];

        $this->assertSame(['actual' => 2, 'anterior' => 1, 'variacion' => ['tipo' => 'aumento', 'diferencia' => 1, 'porcentaje' => 100.0]], $indicador);
    }

    public function test_adopciones_solo_cuentan_solicitudes_aceptadas_por_su_fecha_de_resolucion(): void
    {
        $mascota = $this->mascota();
        $aceptada = $this->solicitud($mascota);
        $cerrada = $this->solicitud($mascota);                      // se cerrará al aceptar la otra
        $this->estado($aceptada, 'aceptada');
        $this->assertSame('cerrada', $cerrada->fresh()->estado);

        // Creada en septiembre, resuelta en octubre: es una adopción de octubre.
        $this->fechas($aceptada, ['created_at' => self::ANTERIOR, 'resuelta_at' => self::PRIMER_INSTANTE]);
        $this->fechas($cerrada, ['resuelta_at' => self::ULTIMO_INSTANTE]);
        $this->fechas($this->resuelta('aceptada'), ['resuelta_at' => self::ANTERIOR]);
        $this->fechas($this->resuelta('aceptada'), ['resuelta_at' => self::DESPUES]);
        $this->fechas($this->resuelta('aceptada'), ['resuelta_at' => self::ANTES]);
        $sinFecha = $this->resuelta('aceptada');
        $this->fechas($sinFecha, ['resuelta_at' => null]);           // fecha no recuperable
        $this->fechas($this->resuelta('rechazada'), ['resuelta_at' => self::ULTIMO_INSTANTE]);
        $this->fechas($this->resuelta('cancelada'), ['resuelta_at' => self::ANTERIOR]);

        $i = $this->indicadores();

        $this->assertSame([1, 1], [$i['adopciones_completadas']['actual'], $i['adopciones_completadas']['anterior']]);
        $this->assertSame('igual', $i['adopciones_completadas']['variacion']['tipo']);
        $this->assertSame([1, 0], [$i['solicitudes_cerradas']['actual'], $i['solicitudes_cerradas']['anterior']], 'el cierre automático no es una adopción');
        $this->assertSame([1, 0], [$i['solicitudes_rechazadas']['actual'], $i['solicitudes_rechazadas']['anterior']]);
        $this->assertSame([0, 1], [$i['solicitudes_canceladas']['actual'], $i['solicitudes_canceladas']['anterior']]);

        // Integración: la vista recibe las adopciones del período por resuelta_at.
        $respuesta = $this->reportes(['periodo' => 'este_mes']);
        $this->assertSame(1, $respuesta->viewData('totalAdopciones'));
        $this->assertSame(['2026-10-01' => 1], $respuesta->viewData('adopcionesPorFecha')->all());
        $this->assertSame($i, $respuesta->viewData('indicadoresPeriodo'));
        // Sin filtro siguen contándose todas las aceptadas, también la que no tiene resuelta_at.
        $this->assertSame(5, $this->reportes()->viewData('totalAdopciones'));
    }

    public function test_las_adopciones_por_dia_usan_la_fecha_de_la_paz(): void
    {
        // 03:30 UTC del 5 de octubre son las 23:30 del 4 de octubre en La Paz.
        $this->fechas($this->resuelta('aceptada'), ['resuelta_at' => '2026-10-05 03:30:00+00']);
        $this->fechas($this->resuelta('aceptada'), ['resuelta_at' => '2026-10-05 04:00:00+00']);

        $this->assertSame(['2026-10-04' => 1, '2026-10-05' => 1], app(ReporteAdministrador::class)->adopcionesPorDia(PeriodoReporte::crear('este_mes'))->all());
    }

    public function test_mascotas_registradas_y_postulaciones_por_fecha_de_alta_y_de_revision(): void
    {
        foreach ([self::PRIMER_INSTANTE, self::DESPUES, self::ANTERIOR, self::ANTERIOR] as $instante) {
            DB::table('mascotas')->where('id', $this->mascota()->id)->update(['created_at' => $instante]);
        }
        $aprobada = $this->postulacion(self::ANTERIOR);
        $this->revisar($aprobada, 'aprobada', self::ULTIMO_INSTANTE);
        $rechazada = $this->postulacion(self::ULTIMO_INSTANTE);
        $this->revisar($rechazada, 'rechazada', self::ANTES);
        $this->postulacion(self::PRIMER_INSTANTE);

        $i = $this->indicadores();

        $this->assertSame([1, 2], [$i['mascotas_registradas']['actual'], $i['mascotas_registradas']['anterior']]);
        $this->assertSame('disminucion', $i['mascotas_registradas']['variacion']['tipo']);
        $this->assertSame(50.0, $i['mascotas_registradas']['variacion']['porcentaje']);
        $this->assertSame([2, 1], [$i['postulaciones_recibidas']['actual'], $i['postulaciones_recibidas']['anterior']]);
        $this->assertSame([1, 0], [$i['postulaciones_aprobadas']['actual'], $i['postulaciones_aprobadas']['anterior']]);
        $this->assertSame([0, 0], [$i['postulaciones_rechazadas']['actual'], $i['postulaciones_rechazadas']['anterior']], 'revisada fuera de ambos períodos');
    }

    public function test_seguimientos_visitas_e_incidencias_por_la_fecha_de_cada_evento(): void
    {
        [$seguimiento, $voluntario] = $this->seguimientoActivo(self::PRIMER_INSTANTE);
        $visita = VisitaSeguimiento::create(['seguimiento_id' => $seguimiento->id, 'voluntario_id' => $voluntario->id, 'fecha_programada' => '2026-10-10', 'estado' => 'realizada', 'fecha_realizada' => '2026-10-10 16:00:00-04']);
        DB::table('visitas_seguimiento')->where('id', $visita->id)->update(['fecha_realizada' => self::ULTIMO_INSTANTE]);
        $incidencia = Incidencia::create(['seguimiento_id' => $seguimiento->id, 'reportado_por' => $voluntario->id, 'tipo' => 'salud', 'descripcion' => 'Revisión', 'estado' => 'pendiente']);
        $incidencia->update(['estado' => 'atendida', 'comentarios_resolucion' => 'Resuelta', 'atendida_por' => $this->admin->id, 'atendida_at' => now()]);
        DB::table('incidencias')->where('id', $incidencia->id)->update(['created_at' => self::ANTERIOR, 'atendida_at' => self::PRIMER_INSTANTE]);
        $seguimiento->update(['estado' => 'cerrado', 'fecha_cierre' => now(), 'observaciones_cierre' => 'Cierre de prueba']);
        DB::table('seguimientos_adopcion')->where('id', $seguimiento->id)->update(['fecha_cierre' => self::DESPUES]);
        $this->seguimientoActivo(self::ANTERIOR);
        $this->seguimientoActivo(self::ANTES);

        $i = $this->indicadores();

        $this->assertSame([1, 1], [$i['seguimientos_iniciados']['actual'], $i['seguimientos_iniciados']['anterior']]);
        $this->assertSame([0, 0], [$i['seguimientos_cerrados']['actual'], $i['seguimientos_cerrados']['anterior']], 'cerrado fuera del intervalo');
        $this->assertSame([1, 0], [$i['visitas_realizadas']['actual'], $i['visitas_realizadas']['anterior']]);
        $this->assertSame([0, 1], [$i['incidencias_reportadas']['actual'], $i['incidencias_reportadas']['anterior']]);
        $this->assertSame([1, 0], [$i['incidencias_atendidas']['actual'], $i['incidencias_atendidas']['anterior']]);
        $this->assertSame(['tipo' => 'nuevo', 'diferencia' => 1, 'porcentaje' => null], $i['incidencias_atendidas']['variacion']);
    }

    public function test_cambio_de_mes_y_de_anio(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-01-15 10:00', PeriodoReporte::ZONA));
        $instantes = [
            '2025-12-01 00:00:00-04' => 'actual',
            '2025-12-31 23:59:59.999999-04' => 'actual',
            '2026-01-01 03:59:59.999999+00' => 'actual',   // 23:59:59.999999 del 31/12 en La Paz
            '2026-01-01 00:00:00-04' => 'fuera',
            '2025-11-30 23:59:59.999999-04' => 'anterior',
            '2025-11-01 00:00:00-04' => 'anterior',
            '2025-10-31 23:59:59.999999-04' => 'fuera',
        ];
        foreach (array_keys($instantes) as $instante) {
            $this->fechas($this->solicitud(), ['created_at' => $instante]);
        }

        $indicador = app(ReporteAdministrador::class)->indicadores(PeriodoReporte::crear('mes_anterior'))['solicitudes_recibidas'];

        $this->assertSame([3, 2], [$indicador['actual'], $indicador['anterior']]);
        $this->assertSame(50.0, $indicador['variacion']['porcentaje']);
    }

    public function test_sin_filtros_se_conserva_el_comportamiento_anterior(): void
    {
        $antigua = $this->resuelta('aceptada');
        $this->fechas($antigua, ['created_at' => '2025-03-02 12:00:00-04', 'resuelta_at' => null]);
        $this->fechas($this->resuelta('aceptada'), ['created_at' => self::PRIMER_INSTANTE]);

        $respuesta = $this->reportes();

        $this->assertNull($respuesta->viewData('indicadoresPeriodo'));
        $this->assertNull($respuesta->viewData('periodoReporte'));
        $this->assertSame(2, $respuesta->viewData('totalAdopciones'));
        // Agrupadas por fecha de creación, como antes.
        $this->assertSame(['2025-03-02', '2026-10-01'], array_keys($respuesta->viewData('adopcionesPorFecha')->all()));
        // Los recuentos por estado describen el estado actual y no dependen del período.
        $this->assertSame($respuesta->viewData('solicitudesPorEstado'), $this->reportes(['periodo' => 'este_mes'])->viewData('solicitudesPorEstado'));
    }

    public function test_solo_el_administrador_activo_accede_a_los_reportes_con_periodo(): void
    {
        $this->reportes(['periodo' => 'este_anio'])->assertOk();
        foreach ([['rol' => 'ciudadano'], ['rol' => 'voluntario'], ['rol' => 'administrador', 'estado' => false]] as $atributos) {
            $this->actingAs(User::factory()->create($atributos))->get(route('admin.reportes.index', ['periodo' => 'este_anio']))->assertForbidden();
        }
        auth()->logout();
        $this->get(route('admin.reportes.index', ['periodo' => 'este_anio']))->assertRedirect(route('login'));
    }

    public function test_una_consulta_por_tabla_sin_importar_el_volumen_de_datos(): void
    {
        $tablas = count((new \ReflectionClassConstant(ReporteAdministrador::class, 'INDICADORES'))->getValue());
        $contar = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->indicadores();
            $consultas = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $consultas;
        };

        $sinDatos = $contar();
        foreach (range(1, 4) as $n) {
            $this->fechas($this->resuelta('aceptada'), ['resuelta_at' => self::PRIMER_INSTANTE]);
            $this->postulacion(self::ANTERIOR);
        }

        $this->assertSame($tablas, $sinDatos, 'el período y el anterior se calculan en la misma consulta');
        $this->assertSame($sinDatos, $contar());
    }

    public function test_la_variacion_no_divide_por_cero(): void
    {
        $this->assertSame(['tipo' => 'igual', 'diferencia' => 0, 'porcentaje' => null], ReporteAdministrador::variacion(0, 0));
        $this->assertSame(['tipo' => 'nuevo', 'diferencia' => 3, 'porcentaje' => null], ReporteAdministrador::variacion(3, 0));
        $this->assertSame(['tipo' => 'disminucion', 'diferencia' => 2, 'porcentaje' => 100.0], ReporteAdministrador::variacion(0, 2));
        $this->assertSame(['tipo' => 'aumento', 'diferencia' => 1, 'porcentaje' => 33.3], ReporteAdministrador::variacion(4, 3));
    }

    // ---------- Datos de prueba ----------

    private function indicadores(): array
    {
        return app(ReporteAdministrador::class)->indicadores(PeriodoReporte::crear('este_mes'));
    }

    private function reportes(array $parametros = [])
    {
        return $this->actingAs($this->admin)->get(route('admin.reportes.index', $parametros))->assertOk();
    }

    private function mascota(): Mascota
    {
        return DB::transaction(function (): Mascota {
            $mascota = Mascota::create(['nombre' => fake()->unique()->firstName(), 'especie' => 'perro', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $this->admin->id]);
            FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => 'mascotas/r.jpg', 'es_principal' => true, 'orden' => 1]);

            return $mascota;
        });
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

    /** Transición real de estado (los triggers de PostgreSQL aplican sus reglas y registran resuelta_at). */
    private function estado(SolicitudAdopcion $solicitud, string $estado): void
    {
        DB::table('solicitudes_adopcion')->where('id', $solicitud->id)->update(['estado' => $estado]);
    }

    private function resuelta(string $estado): SolicitudAdopcion
    {
        $solicitud = $this->solicitud();
        $this->estado($solicitud, $estado);

        return $solicitud;
    }

    /** Fija fechas en la base de pruebas sin tocar estado (el trigger de resolución no interviene). */
    private function fechas(SolicitudAdopcion $solicitud, array $fechas): void
    {
        DB::table('solicitudes_adopcion')->where('id', $solicitud->id)->update($fechas);
    }

    private function postulacion(string $creada): PostulacionVoluntariado
    {
        $postulacion = PostulacionVoluntariado::create([
            'usuario_id' => User::factory()->create(['rol' => 'ciudadano'])->id,
            'estado' => 'pendiente',
            ...CuestionarioVoluntariadoDatos::registro(),
        ]);
        DB::table('postulaciones_voluntariado')->where('id', $postulacion->id)->update(['created_at' => $creada]);

        return $postulacion;
    }

    private function revisar(PostulacionVoluntariado $postulacion, string $estado, string $revisada): void
    {
        DB::table('postulaciones_voluntariado')->where('id', $postulacion->id)->update(['estado' => $estado, 'revisado_por' => $this->admin->id, 'revisado_at' => $revisada]);
    }

    /** @return array{0: SeguimientoAdopcion, 1: User} */
    private function seguimientoActivo(string $inicio): array
    {
        $solicitud = $this->resuelta('aceptada');
        $seguimiento = $solicitud->seguimiento()->firstOrFail();
        $voluntario = User::factory()->create(['rol' => 'voluntario']);
        $seguimiento->update(['voluntario_id' => $voluntario->id, 'estado' => 'activo', 'fecha_inicio' => $inicio]);

        return [$seguimiento, $voluntario];
    }
}
