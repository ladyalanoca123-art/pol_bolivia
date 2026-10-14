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

        $archivo = $carpeta.DIRECTORY_SEPARATOR.'pol_bolivia_'.now()->format('Y-m-d_His').'.dump';

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

        $this->eliminarAntiguos($carpeta, max(1, (int) $this->option('conservar')));
        $this->info('Respaldo creado: '.$archivo);

        return self::SUCCESS;
    }

    private function eliminarAntiguos(string $carpeta, int $conservar): void
    {
        collect(File::glob($carpeta.DIRECTORY_SEPARATOR.'pol_bolivia_*.dump'))
            ->sortDesc()
            ->slice($conservar)
            ->each(fn (string $antiguo) => File::delete($antiguo));
    }
}
