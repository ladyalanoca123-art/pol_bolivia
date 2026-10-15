<?php

namespace Tests\Feature;

use App\Models\Mascota;
use App\Models\User;
use App\Support\PeriodoReporte;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Período de consulta de Reportes: validación (ReportPeriodRequest), datos que recibe la vista y límites
 * en PostgreSQL (PeriodoReporte::aplicar).
 */
class ReportePeriodoTest extends TestCase
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

    public function test_sin_parametros_no_hay_periodo_y_la_vista_actual_sigue_igual(): void
    {
        $respuesta = $this->actingAs($this->admin)->get(route('admin.reportes.index'))->assertOk();

        $this->assertNull($respuesta->viewData('periodoReporte'));
        $this->assertNull($respuesta->viewData('periodoAnterior'));
    }

    /** @return array<string, array{string, array, array}> */
    public static function periodos(): array
    {
        return [
            'este mes' => ['este_mes', ['2026-10-01', '2026-10-14'], ['2026-09-01', '2026-09-14']],
            'mes anterior' => ['mes_anterior', ['2026-09-01', '2026-09-30'], ['2026-08-01', '2026-08-31']],
            'últimos 3 meses' => ['ultimos_3_meses', ['2026-07-15', '2026-10-14'], ['2026-04-14', '2026-07-14']],
            'este año' => ['este_anio', ['2026-01-01', '2026-10-14'], ['2025-01-01', '2025-10-14']],
        ];
    }

    #[DataProvider('periodos')]
    public function test_cada_periodo_llega_a_la_vista_con_su_comparacion(string $tipo, array $fechas, array $anterior): void
    {
        $respuesta = $this->actingAs($this->admin)->get(route('admin.reportes.index', ['periodo' => $tipo]))->assertOk();

        $this->assertSame($tipo, $respuesta->viewData('periodoReporte')->tipo);
        $this->assertSame(array_combine(['fecha_inicio', 'fecha_fin'], $fechas), $respuesta->viewData('periodoReporte')->fechas());
        $this->assertSame(array_combine(['fecha_inicio', 'fecha_fin'], $anterior), $respuesta->viewData('periodoAnterior')->fechas());
    }

    public function test_personalizado_y_compatibilidad_con_el_formulario_de_solo_fechas(): void
    {
        $con = $this->actingAs($this->admin)->get(route('admin.reportes.index', ['periodo' => 'personalizado', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-01-10']))->assertOk();
        $this->assertSame(['fecha_inicio' => '2025-12-22', 'fecha_fin' => '2025-12-31'], $con->viewData('periodoAnterior')->fechas());

        // El formulario actual solo envía fechas: se interpreta como período personalizado.
        $sin = $this->actingAs($this->admin)->get(route('admin.reportes.index', ['fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-05']))->assertOk();
        $this->assertSame('personalizado', $sin->viewData('periodoReporte')->tipo);
        $this->assertSame(5, $sin->viewData('periodoReporte')->dias());
    }

    public function test_en_un_periodo_predefinido_se_ignoran_las_fechas_enviadas(): void
    {
        $respuesta = $this->actingAs($this->admin)->get(route('admin.reportes.index', ['periodo' => 'este_mes', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-01-31']))->assertOk();

        $this->assertSame(['fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-14'], $respuesta->viewData('periodoReporte')->fechas());
    }

    /** @return array<string, array{array, array<int, string>}> */
    public static function entradasInvalidas(): array
    {
        return [
            'período desconocido' => [['periodo' => 'semana'], ['periodo']],
            'personalizado sin fechas' => [['periodo' => 'personalizado'], ['fecha_inicio', 'fecha_fin']],
            'falta la fecha final' => [['fecha_inicio' => '2026-10-01'], ['fecha_fin']],
            'inicio posterior al fin' => [['fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-01'], ['fecha_inicio']],
            'fecha inexistente' => [['fecha_inicio' => '2026-02-30', 'fecha_fin' => '2026-03-01'], ['fecha_inicio']],
            '29 de febrero no bisiesto' => [['fecha_inicio' => '2025-02-28', 'fecha_fin' => '2025-02-29'], ['fecha_fin']],
            'formato día/mes/año' => [['fecha_inicio' => '01/10/2026', 'fecha_fin' => '02/10/2026'], ['fecha_inicio', 'fecha_fin']],
            'fecha futura' => [['fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-15'], ['fecha_fin']],
            'anterior a la fecha mínima' => [['fecha_inicio' => '2019-12-31', 'fecha_fin' => '2020-01-05'], ['fecha_inicio']],
            'más de 366 días' => [['fecha_inicio' => '2024-01-01', 'fecha_fin' => '2025-01-01'], ['fecha_fin']],
        ];
    }

    #[DataProvider('entradasInvalidas')]
    public function test_rechaza_entradas_invalidas(array $parametros, array $errores): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reportes.index', $parametros))
            ->assertRedirect()
            ->assertSessionHasErrors($errores);
    }

    public function test_un_anio_bisiesto_completo_es_valido(): void
    {
        $respuesta = $this->actingAs($this->admin)->get(route('admin.reportes.index', ['fecha_inicio' => '2024-01-01', 'fecha_fin' => '2024-12-31']))->assertOk();

        $this->assertSame(366, $respuesta->viewData('periodoReporte')->dias());
        // Personalizado: se compara con los mismos 366 días inmediatamente anteriores (no con el año 2023,
        // que tiene 365), por lo que empieza el 31/12/2022.
        $this->assertSame(['fecha_inicio' => '2022-12-31', 'fecha_fin' => '2023-12-31'], $respuesta->viewData('periodoAnterior')->fechas());
        $this->assertSame(366, $respuesta->viewData('periodoAnterior')->dias());
    }

    public function test_la_autorizacion_administrativa_se_mantiene(): void
    {
        foreach (['ciudadano', 'voluntario'] as $rol) {
            $this->actingAs(User::factory()->create(['rol' => $rol]))->get(route('admin.reportes.index', ['periodo' => 'este_mes']))->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['rol' => 'administrador', 'estado' => false]))->get(route('admin.reportes.index', ['periodo' => 'este_mes']))->assertForbidden();
        auth()->logout();
        $this->get(route('admin.reportes.index', ['periodo' => 'este_mes']))->assertRedirect(route('login'));
    }

    public function test_los_limites_no_pierden_registros_por_horas_ni_fracciones_de_segundo(): void
    {
        // Columnas TIMESTAMPTZ (mascotas.created_at), con distintas zonas de entrada.
        $instantes = [
            'antes' => '2026-09-30 23:59:59.999999-04',
            'primero' => '2026-10-01 00:00:00-04',
            'ultimo' => '2026-10-14 23:59:59.999999-04',
            'ultimo_en_utc' => '2026-10-15 03:59:59.999999+00',
            'despues' => '2026-10-15 00:00:00-04',
        ];
        $ids = [];
        foreach ($instantes as $clave => $instante) {
            $ids[$clave] = Mascota::create(['nombre' => "Límite {$clave}", 'especie' => 'perro', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $this->admin->id])->id;
            DB::table('mascotas')->where('id', $ids[$clave])->update(['created_at' => $instante]);
        }

        $periodo = PeriodoReporte::crear('este_mes');
        $dentro = $periodo->aplicar(Mascota::query()->whereIn('id', $ids), 'created_at')->pluck('id')->sort()->values()->all();
        $this->assertSame([$ids['primero'], $ids['ultimo'], $ids['ultimo_en_utc']], $dentro);

        // Mismo resultado aunque la sesión de PostgreSQL esté en UTC: los límites llevan su desplazamiento.
        DB::statement("SET LOCAL TimeZone = 'UTC'");
        $this->assertSame($dentro, $periodo->aplicar(Mascota::query()->whereIn('id', $ids), 'created_at')->pluck('id')->sort()->values()->all());

        // Comparación: el anterior (1–14 de septiembre) no se solapa con el actual.
        $this->assertSame([], $periodo->anterior()->aplicar(Mascota::query()->whereIn('id', [$ids['primero'], $ids['ultimo']]), 'created_at')->pluck('id')->all());
    }

    public function test_la_etiqueta_del_periodo_esta_en_espanol(): void
    {
        $this->assertSame('Este mes (1 al 14 de octubre de 2026)', PeriodoReporte::crear('este_mes')->etiqueta());
        $this->assertSame('Últimos 3 meses (15 de julio al 14 de octubre de 2026)', PeriodoReporte::crear('ultimos_3_meses')->etiqueta());
        $this->assertSame('Personalizado (22 de diciembre de 2025 al 10 de enero de 2026)', PeriodoReporte::crear('personalizado', '2025-12-22', '2026-01-10')->etiqueta());
        $this->assertSame('Personalizado (14 de octubre de 2026)', PeriodoReporte::crear('personalizado', '2026-10-14', '2026-10-14')->etiqueta());
    }
}
