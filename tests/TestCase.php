<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /** Única base en la que las pruebas pueden escribir o recrear tablas. */
    public const BASE_DE_PRUEBAS = 'pol_bolivia_test';

    /** Comentario de PostgreSQL que identifica la base de pruebas (COMMENT ON DATABASE). */
    public const MARCA_DE_PRUEBAS = 'POL-BOLIVIA-PRUEBAS';

    /**
     * Freno de seguridad: se ejecuta ANTES de RefreshDatabase / DatabaseMigrations (migrate:fresh)
     * y de cualquier otro trait de base de datos.
     *
     * Se usa setUpTraits() porque los métodos de un trait (p. ej. beforeRefreshingDatabase() de
     * RefreshDatabase) prevalecen sobre los heredados de esta clase y anularían el freno.
     */
    protected function setUpTraits()
    {
        $this->protegerBaseDePruebas();

        return parent::setUpTraits();
    }

    /**
     * Pregunta al propio servidor qué base está conectada y cuál es su marca: no depende del
     * nombre del entorno ni de las variables de configuración.
     */
    protected function protegerBaseDePruebas(): void
    {
        $cacheada = $this->app->configurationIsCached();
        $conexion = $cacheada ? null : DB::connection();
        $identidad = $conexion?->selectOne(
            "select current_database() as base, shobj_description(d.oid, 'pg_database') as marca
             from pg_database d where d.datname = current_database()"
        );

        $motivo = static::motivoParaDetenerPruebas($cacheada, $conexion?->getDriverName(), $identidad?->base, $identidad?->marca);

        if ($motivo !== null) {
            fwrite(STDERR, PHP_EOL."PRUEBAS DETENIDAS: {$motivo}".PHP_EOL);

            throw new RuntimeException("PRUEBAS DETENIDAS: {$motivo}");
        }
    }

    /** Devuelve el motivo para detener las pruebas, o null si la base es la de pruebas. */
    public static function motivoParaDetenerPruebas(bool $configuracionCacheada, ?string $driver, ?string $base, ?string $marca): ?string
    {
        return match (true) {
            $configuracionCacheada => 'hay una configuración cacheada (bootstrap/cache/config.php); ejecuta php artisan config:clear.',
            $driver !== 'pgsql' => "el driver de base de datos es '{$driver}', no pgsql.",
            $base !== self::BASE_DE_PRUEBAS => "la base conectada es '{$base}', no ".self::BASE_DE_PRUEBAS.'.',
            $marca !== self::MARCA_DE_PRUEBAS => "la base {$base} no tiene la marca ".self::MARCA_DE_PRUEBAS.'.',
            default => null,
        };
    }
}
