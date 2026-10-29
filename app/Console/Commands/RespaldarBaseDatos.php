<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Crea un respaldo comprimido de la base de datos PostgreSQL con pg_dump.
 *
 * Uso manual:      php artisan respaldo:base-datos
 * Uso automático:  programado a diario en routes/console.php
 *                  (requiere "php artisan schedule:work" o una tarea programada del sistema).
 */
class RespaldarBaseDatos extends Command
{
    protected $signature = 'respaldo:base-datos {--conservar=7 : Cantidad de respaldos que se mantienen}';

    protected $description = 'Genera un respaldo de la base de datos PostgreSQL y elimina los más antiguos';

    public function handle(): int
    {
        $db = config('database.connections.pgsql');
        $carpeta = storage_path('app/private/respaldos');
        File::ensureDirectoryExists($carpeta);

        // El nombre lleva la base respaldada: así un respaldo de otra base (p. ej. pol_bolivia_manual)
        // no se confunde con los de la base real ni entra en su rotación.
        $base = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $db['database']);
        $archivo = $carpeta.DIRECTORY_SEPARATOR.$base.'_'.now()->format('Y-m-d_His').'.dump';

        // La contraseña va por variable de entorno, nunca en la línea de comandos.
        $resultado = Process::env(['PGPASSWORD' => (string) $db['password']])
            ->timeout(600)
            ->run([
                $db['dump_path'] ?? 'pg_dump',
                '--host='.$db['host'],
                '--port='.$db['port'],
                '--username='.$db['username'],
                '--format=custom',
                '--no-owner',
                '--file='.$archivo,
                $db['database'],
            ]);

        if ($resultado->failed()) {
            File::delete($archivo);
            $this->error('No se pudo crear el respaldo: '.trim($resultado->errorOutput()));

            return self::FAILURE;
        }

        $this->eliminarAntiguos($carpeta, $base, max(1, (int) $this->option('conservar')));
        $this->info('Respaldo creado: '.$archivo);

        return self::SUCCESS;
    }

    /** Conserva los $conservar respaldos más recientes de esta base; los de otras bases no se tocan. */
    private function eliminarAntiguos(string $carpeta, string $base, int $conservar): void
    {
        // «{base}_» seguido de la fecha: pol_bolivia_2026-… no incluye pol_bolivia_manual_2026-…
        collect(File::glob($carpeta.DIRECTORY_SEPARATOR.$base.'_[0-9]*.dump'))
            ->sortDesc()
            ->slice($conservar)
            ->each(fn (string $antiguo) => File::delete($antiguo));
    }
}
