<?php

namespace Tests\Unit;

use App\Support\PeriodoReporte;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cálculo de períodos de Reportes y de su período anterior (sin base de datos).
 */
class PeriodoReporteTest extends TestCase
{
    /** @return array<string, array{string, string, string, string, string, string}> tipo, hoy, inicio, fin, anterior inicio, anterior fin */
    public static function periodos(): array
    {
        return [
            'este mes' => ['este_mes', '2026-10-14', '2026-10-01', '2026-10-14', '2026-09-01', '2026-09-14'],
            'este mes el día 1' => ['este_mes', '2026-10-01', '2026-10-01', '2026-10-01', '2026-09-01', '2026-09-01'],
            'este mes de 31 días frente a febrero' => ['este_mes', '2026-03-31', '2026-03-01', '2026-03-31', '2026-02-01', '2026-02-28'],
            'este mes frente a febrero bisiesto' => ['este_mes', '2024-03-31', '2024-03-01', '2024-03-31', '2024-02-01', '2024-02-29'],
            'este mes en enero (cambio de año)' => ['este_mes', '2026-01-20', '2026-01-01', '2026-01-20', '2025-12-01', '2025-12-20'],
            'mes anterior' => ['mes_anterior', '2026-10-14', '2026-09-01', '2026-09-30', '2026-08-01', '2026-08-31'],
            'mes anterior desde el día 31' => ['mes_anterior', '2026-03-31', '2026-02-01', '2026-02-28', '2026-01-01', '2026-01-31'],
            'mes anterior bisiesto' => ['mes_anterior', '2024-03-10', '2024-02-01', '2024-02-29', '2024-01-01', '2024-01-31'],
            'mes anterior en enero (cambio de año)' => ['mes_anterior', '2026-01-15', '2025-12-01', '2025-12-31', '2025-11-01', '2025-11-30'],
            'últimos 3 meses' => ['ultimos_3_meses', '2026-10-14', '2026-07-15', '2026-10-14', '2026-04-14', '2026-07-14'],
            'últimos 3 meses desde el 31 de mayo' => ['ultimos_3_meses', '2026-05-31', '2026-03-01', '2026-05-31', '2025-11-29', '2026-02-28'],
            'últimos 3 meses bisiesto' => ['ultimos_3_meses', '2024-05-31', '2024-03-01', '2024-05-31', '2023-11-30', '2024-02-29'],
            'últimos 3 meses cruzando el año' => ['ultimos_3_meses', '2026-02-10', '2025-11-11', '2026-02-10', '2025-08-11', '2025-11-10'],
            'este año' => ['este_anio', '2026-10-14', '2026-01-01', '2026-10-14', '2025-01-01', '2025-10-14'],
            'este año el 1 de enero' => ['este_anio', '2026-01-01', '2026-01-01', '2026-01-01', '2025-01-01', '2025-01-01'],
            'este año hasta el 29 de febrero' => ['este_anio', '2028-02-29', '2028-01-01', '2028-02-29', '2027-01-01', '2027-02-28'],
            'este año completo bisiesto' => ['este_anio', '2024-12-31', '2024-01-01', '2024-12-31', '2023-01-01', '2023-12-31'],
        ];
    }

    #[DataProvider('periodos')]
    public function test_calcula_el_periodo_y_su_anterior(string $tipo, string $hoy, string $inicio, string $fin, string $antInicio, string $antFin): void
    {
        $periodo = PeriodoReporte::crear($tipo, ahora: CarbonImmutable::parse("{$hoy} 10:00", PeriodoReporte::ZONA));
        $anterior = $periodo->anterior();

        $this->assertSame(['fecha_inicio' => $inicio, 'fecha_fin' => $fin], $periodo->fechas());
        $this->assertSame(['fecha_inicio' => $antInicio, 'fecha_fin' => $antFin], $anterior->fechas());
        $this->assertSame($tipo, $anterior->tipo);
        // El anterior termina justo donde empieza el período: sin hueco ni solapamiento.
        $this->assertTrue($anterior->finExclusivo->lessThanOrEqualTo($periodo->inicio));
    }

    public function test_los_periodos_moviles_y_personalizados_comparan_el_mismo_numero_de_dias_contiguos(): void
    {
        $hoy = CarbonImmutable::parse('2026-10-14 10:00', PeriodoReporte::ZONA);
        foreach ([PeriodoReporte::crear('ultimos_3_meses', ahora: $hoy), PeriodoReporte::crear('personalizado', '2026-01-01', '2026-01-10', $hoy)] as $periodo) {
            $anterior = $periodo->anterior();
            $this->assertSame($periodo->dias(), $anterior->dias());
            $this->assertTrue($anterior->finExclusivo->equalTo($periodo->inicio));
        }
        $this->assertSame(['fecha_inicio' => '2025-12-22', 'fecha_fin' => '2025-12-31'], PeriodoReporte::crear('personalizado', '2026-01-01', '2026-01-10', $hoy)->anterior()->fechas());
    }

    public function test_los_limites_son_un_intervalo_semiabierto_en_la_paz(): void
    {
        $periodo = PeriodoReporte::crear('este_mes', ahora: CarbonImmutable::parse('2026-10-14 10:00', PeriodoReporte::ZONA));

        $this->assertSame('2026-10-01 00:00:00-04:00', $periodo->inicio->format('Y-m-d H:i:sP'));
        $this->assertSame('2026-10-15 00:00:00-04:00', $periodo->finExclusivo->format('Y-m-d H:i:sP'));
        $this->assertSame('America/La_Paz', $periodo->inicio->getTimezone()->getName());
        $this->assertSame(14, $periodo->dias());
    }

    public function test_hoy_se_determina_en_la_paz_aunque_el_instante_llegue_en_utc(): void
    {
        // 02:30 UTC del 15/10 son las 22:30 del 14/10 en La Paz; 04:30 UTC del 01/11 son las 00:30 del 01/11.
        $this->assertSame('2026-10-14', PeriodoReporte::crear('este_mes', ahora: CarbonImmutable::parse('2026-10-15 02:30', 'UTC'))->fechas()['fecha_fin']);
        $this->assertSame(['fecha_inicio' => '2026-11-01', 'fecha_fin' => '2026-11-01'], PeriodoReporte::crear('este_mes', ahora: CarbonImmutable::parse('2026-11-01 04:30', 'UTC'))->fechas());
        // Último instante del día en La Paz.
        $this->assertSame('2026-12-31', PeriodoReporte::crear('este_anio', ahora: CarbonImmutable::parse('2026-12-31 23:59:59.999999', PeriodoReporte::ZONA))->fechas()['fecha_fin']);
    }

    public function test_personalizado_admite_un_anio_bisiesto_completo_y_rechaza_mas_dias(): void
    {
        $hoy = CarbonImmutable::parse('2026-10-14 10:00', PeriodoReporte::ZONA);

        $this->assertSame(366, PeriodoReporte::crear('personalizado', '2024-01-01', '2024-12-31', $hoy)->dias());
        $this->assertSame(366, PeriodoReporte::crear('personalizado', '2023-01-01', '2024-01-01', $hoy)->dias());
        $this->assertSame(1, PeriodoReporte::crear('personalizado', '2026-10-14', '2026-10-14', $hoy)->dias());

        $this->expectException(InvalidArgumentException::class);
        PeriodoReporte::crear('personalizado', '2023-01-01', '2024-01-02', $hoy);
    }

    /** @return array<string, array{string, ?string, ?string}> */
    public static function entradasInvalidas(): array
    {
        return [
            'tipo desconocido' => ['semana', null, null],
            'sin fechas' => ['personalizado', null, null],
            'falta la fecha final' => ['personalizado', '2026-10-01', null],
            'inicio posterior al fin' => ['personalizado', '2026-10-10', '2026-10-01'],
            'mes inexistente' => ['personalizado', '2026-13-01', '2026-10-01'],
            '30 de febrero' => ['personalizado', '2026-02-30', '2026-03-01'],
            '29 de febrero en año no bisiesto' => ['personalizado', '2025-02-29', '2025-03-01'],
            'formato con hora' => ['personalizado', '2026-10-01 00:00', '2026-10-02'],
            'formato día/mes/año' => ['personalizado', '01/10/2026', '02/10/2026'],
            'anterior a la fecha mínima' => ['personalizado', '2019-12-31', '2020-01-10'],
            'fecha futura' => ['personalizado', '2026-10-10', '2026-10-15'],
        ];
    }

    #[DataProvider('entradasInvalidas')]
    public function test_rechaza_entradas_invalidas(string $tipo, ?string $desde, ?string $hasta): void
    {
        $this->expectException(InvalidArgumentException::class);

        PeriodoReporte::crear($tipo, $desde, $hasta, CarbonImmutable::parse('2026-10-14 10:00', PeriodoReporte::ZONA));
    }

    public function test_en_los_periodos_predefinidos_se_ignoran_las_fechas(): void
    {
        $periodo = PeriodoReporte::crear('mes_anterior', '2020-01-01', '2020-01-31', CarbonImmutable::parse('2026-10-14 10:00', PeriodoReporte::ZONA));

        $this->assertSame(['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-09-30'], $periodo->fechas());
    }
}
