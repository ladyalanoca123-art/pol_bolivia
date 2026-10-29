<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * php artisan respaldo:base-datos: el archivo lleva el nombre de la base y la rotación solo borra
 * respaldos de esa misma base. Usa una carpeta temporal propia: nunca toca storage/app/private/respaldos.
 */
class RespaldoBaseDatosTest extends TestCase
{
    private string $carpetaTemporal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->carpetaTemporal = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pol_respaldos_'.uniqid();
        $this->app->useStoragePath($this->carpetaTemporal);
        File::ensureDirectoryExists($this->carpetaTemporal.'/app/private/respaldos');
        // pg_dump simulado: no se respalda ninguna base real.
        Process::fake();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->carpetaTemporal);
        parent::tearDown();
    }

    public function test_el_respaldo_lleva_el_nombre_de_la_base_y_solo_rota_los_suyos(): void
    {
        config(['database.connections.pgsql.database' => 'pol_bolivia']);
        $carpeta = $this->carpetaTemporal.'/app/private/respaldos';
        $propios = ['pol_bolivia_2026-10-01_010000.dump', 'pol_bolivia_2026-10-02_010000.dump', 'pol_bolivia_2026-10-03_010000.dump'];
        $ajenos = ['pol_bolivia_manual_2026-09-01_010000.dump', 'pol_bolivia_manual_2026-09-02_010000.dump', 'otra_base_2026-08-01_010000.dump'];
        foreach ([...$propios, ...$ajenos] as $nombre) {
            File::put("$carpeta/$nombre", 'x');
        }

        $this->artisan('respaldo:base-datos', ['--conservar' => 2])->assertSuccessful();

        Process::assertRan(fn ($proceso) => str_contains(implode(' ', (array) $proceso->command), DIRECTORY_SEPARATOR.'pol_bolivia_'.now()->format('Y-m-d')));
        // De la base real quedan los 2 más recientes; el más antiguo se borra.
        $this->assertFileDoesNotExist("$carpeta/pol_bolivia_2026-10-01_010000.dump");
        $this->assertFileExists("$carpeta/pol_bolivia_2026-10-02_010000.dump");
        $this->assertFileExists("$carpeta/pol_bolivia_2026-10-03_010000.dump");
        // Los respaldos de otras bases no cuentan en la rotación ni se borran.
        foreach ($ajenos as $nombre) {
            $this->assertFileExists("$carpeta/$nombre");
        }
    }

    public function test_un_respaldo_de_otra_base_no_borra_los_de_la_base_real(): void
    {
        config(['database.connections.pgsql.database' => 'pol_bolivia_manual']);
        $carpeta = $this->carpetaTemporal.'/app/private/respaldos';
        foreach (range(1, 5) as $dia) {
            File::put("$carpeta/pol_bolivia_2026-10-0{$dia}_010000.dump", 'x');
        }

        $this->artisan('respaldo:base-datos', ['--conservar' => 1])->assertSuccessful();

        Process::assertRan(fn ($proceso) => str_contains(implode(' ', (array) $proceso->command), 'pol_bolivia_manual_'));
        $this->assertCount(5, File::glob("$carpeta/pol_bolivia_2026-*.dump"));
    }
}
