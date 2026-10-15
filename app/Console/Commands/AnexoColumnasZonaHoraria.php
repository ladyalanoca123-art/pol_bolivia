<?php

namespace App\Console\Commands;

use App\Support\ZonaHoraria\AnexoColumnas;
use App\Support\ZonaHoraria\ReparacionZonaHoraria;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Genera el anexo firmado con las columnas originales de un registro de reparación (AnexoColumnas).
 * No modifica la base (transacción de solo lectura) ni el registro: solo crea un archivo nuevo junto a él.
 * Debe generarse antes de añadir columnas a las tablas reparadas, mientras las huellas coinciden.
 */
class AnexoColumnasZonaHoraria extends Command
{
    protected $signature = 'zona-horaria:anexo-columnas
        {--manifiesto= : Registro de cambios de la reparación (obligatorio)}
        {--base= : Nombre de la base conectada (obligatorio)}';

    protected $description = 'Guarda, sin modificar la base, el anexo firmado con las columnas originales de un registro de reparación';

    public function handle(): int
    {
        $identidad = ReparacionZonaHoraria::identidad();
        $motivo = ReparacionZonaHoraria::motivoRechazoBase($identidad->base, $identidad->marca, $this->option('base'), null, false);
        if ($motivo !== null) {
            $this->error("Rechazado: {$motivo}");

            return self::FAILURE;
        }

        $manifiesto = (string) $this->option('manifiesto');
        $ruta = AnexoColumnas::rutaPara($manifiesto);
        $antes = is_file($manifiesto) ? hash_file('sha256', $manifiesto) : null;

        DB::beginTransaction();
        try {
            ReparacionZonaHoraria::prepararTransaccion(true);
            $anexo = AnexoColumnas::generar($manifiesto);
        } catch (Throwable $e) {
            $this->error('Rechazado: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            DB::rollBack();
        }

        try {
            AnexoColumnas::guardar($ruta, $anexo);
        } catch (Throwable $e) {
            $this->error('Rechazado: '.$e->getMessage());

            return self::FAILURE;
        }

        // El registro original no debe haber cambiado.
        if (hash_file('sha256', $manifiesto) !== $antes) {
            $this->error('El registro original cambió durante la generación del anexo.');

            return self::FAILURE;
        }

        foreach ($anexo['columnas'] as $tabla => $columnas) {
            $this->line("  {$tabla}: ".count($columnas).' columnas');
        }
        $this->info("Anexo guardado ({$anexo['huellas_comprobadas']} huellas comprobadas): {$ruta}");

        return self::SUCCESS;
    }
}
