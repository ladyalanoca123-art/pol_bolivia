<?php

namespace Tests\Unit;

use App\Support\ZonaHoraria\PlanReparacion;
use App\Support\ZonaHoraria\ReparacionZonaHoraria;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Reparación de zona horaria sin base de datos: bases autorizadas y plan fijo de octubre de 2026.
 * Las pruebas del plan real solo se ejecutan donde existe su archivo local (no se versiona).
 */
class ReparacionZonaHorariaPlanTest extends TestCase
{
    public function test_la_simulacion_se_admite_en_las_bases_conocidas(): void
    {
        foreach (array_keys(ReparacionZonaHoraria::BASES) as $base) {
            $this->assertNull(ReparacionZonaHoraria::motivoRechazoBase($base, null, $base, null, false), $base);
        }
    }

    public function test_exige_indicar_la_base_y_que_coincida_con_la_conectada(): void
    {
        $this->assertStringContainsString('--base=', ReparacionZonaHoraria::motivoRechazoBase('pol_bolivia_test', 'POL-BOLIVIA-PRUEBAS', null, null, false));
        $this->assertStringContainsString('--base=', ReparacionZonaHoraria::motivoRechazoBase('pol_bolivia_test', 'POL-BOLIVIA-PRUEBAS', '', null, false));
        $this->assertStringContainsString('no coincide', ReparacionZonaHoraria::motivoRechazoBase('pol_bolivia_test', 'POL-BOLIVIA-PRUEBAS', 'pol_bolivia', 'pol_bolivia', true));
    }

    public function test_rechaza_bases_no_autorizadas_incluso_en_simulacion(): void
    {
        foreach (['postgres', 'pol_bolivia_copia', 'otra'] as $base) {
            $this->assertStringContainsString('no está autorizada', ReparacionZonaHoraria::motivoRechazoBase($base, 'POL-BOLIVIA-PRUEBAS', $base, $base, false));
            $this->assertStringContainsString('no está autorizada', ReparacionZonaHoraria::motivoRechazoBase($base, 'POL-BOLIVIA-PRUEBAS', $base, $base, true));
        }
    }

    public function test_aplicar_exige_la_marca_de_la_base(): void
    {
        $this->assertNull(ReparacionZonaHoraria::motivoRechazoBase('pol_bolivia_test', 'POL-BOLIVIA-PRUEBAS', 'pol_bolivia_test', null, true));
        $this->assertNull(ReparacionZonaHoraria::motivoRechazoBase('pol_bolivia_ensayo_zona', 'POL-BOLIVIA-ENSAYO-ZONA', 'pol_bolivia_ensayo_zona', null, true));

        $this->assertStringContainsString('marca', ReparacionZonaHoraria::motivoRechazoBase('pol_bolivia_test', null, 'pol_bolivia_test', null, true));
        $this->assertStringContainsString('marca', ReparacionZonaHoraria::motivoRechazoBase('pol_bolivia_test', 'POL-BOLIVIA-ENSAYO-ZONA', 'pol_bolivia_test', null, true));
        $this->assertStringContainsString('marca', ReparacionZonaHoraria::motivoRechazoBase('pol_bolivia_ensayo_zona', 'POL-BOLIVIA-PRUEBAS', 'pol_bolivia_ensayo_zona', null, true));
    }

    public function test_aplicar_en_desarrollo_exige_confirmar_la_base(): void
    {
        $this->assertStringContainsString('--confirmar-base=pol_bolivia', ReparacionZonaHoraria::motivoRechazoBase('pol_bolivia', null, 'pol_bolivia', null, true));
        $this->assertStringContainsString('--confirmar-base=pol_bolivia', ReparacionZonaHoraria::motivoRechazoBase('pol_bolivia', null, 'pol_bolivia', 'pol_bolivia_test', true));
        $this->assertNull(ReparacionZonaHoraria::motivoRechazoBase('pol_bolivia', null, 'pol_bolivia', 'pol_bolivia', true));
    }

    public function test_el_plan_de_octubre_tiene_exactamente_las_29_entradas_y_las_31_exclusiones(): void
    {
        $this->planLocal();
        $plan = new PlanReparacion;

        $this->assertSame([], $plan->errores());
        $this->assertCount(29, $plan->entradas());
        $this->assertSame(29, $plan->total());
        $this->assertCount(31, $plan->exclusiones());
        $this->assertSame(
            ['indeterminado' => 19, 'sospechoso' => 12],
            collect($plan->exclusiones())->countBy('categoria')->sortKeys()->all()
        );
        $this->assertSame('98d53efc2bc3d44e5d9b16b1d76b1991abbf564601b3d0a2d1169822a7c69b8e', $plan->hash());
    }

    public function test_ninguna_exclusion_figura_entre_las_entradas(): void
    {
        $plan = $this->planLocal();
        $entradas = array_map(fn ($e) => PlanReparacion::clave($e), $plan['entradas']);
        $exclusiones = array_map(fn ($x) => PlanReparacion::clave($x), $plan['exclusiones']);

        $this->assertSame([], array_values(array_intersect($entradas, $exclusiones)));
        $this->assertCount(29, array_unique($entradas));
    }

    public function test_cada_entrada_resta_exactamente_cuatro_horas_y_coincide_con_su_evidencia(): void
    {
        foreach ($this->planLocal()['entradas'] as $e) {
            $actual = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $e['actual'], new \DateTimeZone('UTC'));
            $nuevo = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $e['nuevo'], new \DateTimeZone('UTC'));

            $this->assertSame(14400, $actual->getTimestamp() - $nuevo->getTimestamp(), PlanReparacion::clave($e));
            $this->assertSame($e['nuevo'], $e['evidencia']['valor'], PlanReparacion::clave($e));
            $this->assertContains($e['columna'], PlanReparacion::COLUMNAS_PERMITIDAS[$e['tabla']]);
        }
    }

    public function test_un_plan_alterado_no_es_valido(): void
    {
        $plan = $this->planLocal();
        $entradas = $plan['entradas'];

        // Una entrada de menos.
        $this->assertNotSame([], (new PlanReparacion(array_slice($entradas, 1)))->errores());

        // Incluir una exclusión.
        $exclusion = $plan['exclusiones'][0];
        $conExcluida = $entradas;
        $conExcluida[0] = [...$entradas[0], 'tabla' => $exclusion['tabla'], 'columna' => $exclusion['columna'], 'id' => $exclusion['id']];
        $this->assertContains(
            'Entrada 1 ('.PlanReparacion::clave($exclusion).'): está en la lista de exclusiones y no puede repararse.',
            (new PlanReparacion($conExcluida))->errores()
        );

        // Desfase distinto de 4 h.
        $desfase = $entradas;
        $desfase[0]['nuevo'] = '2000-01-01 00:00:00';
        $this->assertNotSame([], (new PlanReparacion($desfase))->errores());

        // Columna no permitida.
        $columna = $entradas;
        $columna[0]['columna'] = 'email_verified_at';
        $this->assertNotSame([], (new PlanReparacion($columna))->errores());

        // Cualquier cambio altera el hash que exige --confirmar-plan.
        $this->assertNotSame((new PlanReparacion)->hash(), (new PlanReparacion($desfase))->hash());
    }

    public function test_el_plan_local_solo_se_acepta_si_existe_y_conserva_su_huella(): void
    {
        $datos = ['total' => 1, 'entradas' => [['tabla' => 'usuarios', 'columna' => 'created_at', 'id' => 1]], 'exclusiones' => []];
        $huella = hash('sha256', json_encode(['entradas' => $datos['entradas'], 'exclusiones' => $datos['exclusiones'], 'total' => $datos['total']]));
        $ruta = tempnam(sys_get_temp_dir(), 'plan');

        try {
            file_put_contents($ruta, '<?php return '.var_export($datos, true).';');
            $this->assertSame($datos['entradas'], PlanReparacion::cargarPlanLocal($ruta, $huella)['entradas']);

            // Con la huella del plan de octubre (otros datos) se rechaza.
            $this->assertRechazado(fn () => PlanReparacion::cargarPlanLocal($ruta), 'no coincide con la huella esperada');

            // Un dato alterado cambia la huella.
            $alterado = $datos;
            $alterado['entradas'][0]['id'] = 2;
            file_put_contents($ruta, '<?php return '.var_export($alterado, true).';');
            $this->assertRechazado(fn () => PlanReparacion::cargarPlanLocal($ruta, $huella), 'no coincide con la huella esperada');

            // Formato incorrecto.
            file_put_contents($ruta, '<?php return ["entradas" => []];');
            $this->assertRechazado(fn () => PlanReparacion::cargarPlanLocal($ruta, $huella), 'no tiene el formato esperado');
        } finally {
            @unlink($ruta);
        }

        // Archivo inexistente.
        $this->assertRechazado(fn () => PlanReparacion::cargarPlanLocal($ruta, $huella), 'No se encontró el plan local');
    }

    /** Los datos reales del plan solo existen en el equipo de la reparación: sin ellos, la prueba se omite. */
    private function planLocal(): array
    {
        if (! is_file(dirname(__DIR__, 2).'/'.PlanReparacion::RUTA_PLAN_LOCAL)) {
            $this->markTestSkipped('Plan local de octubre de 2026 no disponible: sus datos no se versionan.');
        }

        // Si el archivo existe, debe conservar su huella: un plan alterado hace fallar la prueba.
        return PlanReparacion::cargarPlanLocal();
    }

    private function assertRechazado(callable $accion, string $mensaje): void
    {
        try {
            $accion();
            $this->fail('Debía rechazarse el plan local.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($mensaje, $e->getMessage());
        }
    }
}
