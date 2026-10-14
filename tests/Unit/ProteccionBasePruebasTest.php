<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\TestCase as TestCaseProyecto;

/**
 * Decisión del freno de seguridad de las pruebas (sin base de datos).
 */
class ProteccionBasePruebasTest extends TestCase
{
    public function test_permite_solo_la_base_de_pruebas_marcada(): void
    {
        $this->assertNull(TestCaseProyecto::motivoParaDetenerPruebas(false, 'pgsql', 'pol_bolivia_test', 'POL-BOLIVIA-PRUEBAS'));
    }

    public function test_detiene_con_la_base_de_desarrollo(): void
    {
        $this->assertStringContainsString("'pol_bolivia'", TestCaseProyecto::motivoParaDetenerPruebas(false, 'pgsql', 'pol_bolivia', null));
        // Aunque la base de desarrollo tuviera la marca, el nombre no coincide.
        $this->assertNotNull(TestCaseProyecto::motivoParaDetenerPruebas(false, 'pgsql', 'pol_bolivia', 'POL-BOLIVIA-PRUEBAS'));
    }

    public function test_detiene_si_la_marca_no_coincide_o_falta(): void
    {
        $this->assertStringContainsString('marca', TestCaseProyecto::motivoParaDetenerPruebas(false, 'pgsql', 'pol_bolivia_test', null));
        $this->assertStringContainsString('marca', TestCaseProyecto::motivoParaDetenerPruebas(false, 'pgsql', 'pol_bolivia_test', 'OTRA'));
        $this->assertNotNull(TestCaseProyecto::motivoParaDetenerPruebas(false, 'pgsql', 'pol_bolivia_test', 'pol-bolivia-pruebas'));
    }

    public function test_detiene_con_configuracion_cacheada(): void
    {
        // Aun con base y marca correctas: una configuración cacheada ignora phpunit.xml.
        $this->assertStringContainsString('cacheada', TestCaseProyecto::motivoParaDetenerPruebas(true, 'pgsql', 'pol_bolivia_test', 'POL-BOLIVIA-PRUEBAS'));
    }

    public function test_detiene_con_otro_driver(): void
    {
        $this->assertStringContainsString('driver', TestCaseProyecto::motivoParaDetenerPruebas(false, 'sqlite', 'pol_bolivia_test', 'POL-BOLIVIA-PRUEBAS'));
    }
}
