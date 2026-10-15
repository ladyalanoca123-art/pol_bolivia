<?php

namespace App\Console\Commands;

use App\Support\ZonaHoraria\AnexoColumnas;
use App\Support\ZonaHoraria\DiscrepanciaException;
use App\Support\ZonaHoraria\PlanReparacion;
use App\Support\ZonaHoraria\RegistroCambios;
use App\Support\ZonaHoraria\ReparacionZonaHoraria;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Revierte una reparación a partir de su registro de cambios firmado.
 * Por defecto SIMULA. Para modificar exige --aplicar, --base, --confirmar-plan (hash del plan del
 * registro) y una base autorizada. Solo revierte si cada valor sigue siendo el que dejó la reparación.
 */
class RevertirZonaHoraria extends Command
{
    protected $signature = 'zona-horaria:revertir
        {--manifiesto= : Registro de cambios de la reparación (obligatorio)}
        {--base= : Nombre de la base conectada (obligatorio)}
        {--aplicar : Revierte los cambios (sin esta opción solo simula)}
        {--confirmar-plan= : Hash del plan que figura en el registro}
        {--confirmar-base= : Confirmación adicional para la base sin marca (pol_bolivia)}
        {--anexo= : Anexo firmado con las columnas originales (zona-horaria:anexo-columnas), si después se añadieron columnas}';

    protected $description = 'Revierte una reparación de zona horaria desde su registro de cambios; simula por defecto';

    public function handle(ReparacionZonaHoraria $reparacion): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $identidad = ReparacionZonaHoraria::identidad();

        $motivo = ReparacionZonaHoraria::motivoRechazoBase($identidad->base, $identidad->marca, $this->option('base'), $this->option('confirmar-base'), $aplicar);
        if ($motivo !== null) {
            $this->error("Rechazado: {$motivo}");

            return self::FAILURE;
        }

        try {
            $registro = RegistroCambios::leer((string) $this->option('manifiesto'));
        } catch (Throwable $e) {
            $this->error('Rechazado: '.$e->getMessage());

            return self::FAILURE;
        }

        $plan = $this->laravel->make(PlanReparacion::class);
        $problema = match (true) {
            ($registro['tipo'] ?? null) !== 'reparacion' || ($registro['version'] ?? null) !== 1 => 'el registro no es de una reparación (versión 1).',
            // 'confirmando': el proceso pudo interrumpirse tras el COMMIT; la verificación de cada
            // fila decide si la reparación llegó a aplicarse.
            ! in_array($registro['estado'] ?? null, ['aplicado', 'confirmando'], true) => 'la reparación del registro no figura como aplicada.',
            ($registro['base'] ?? null) !== $identidad->base => 'el registro es de la base '.($registro['base'] ?? '(ninguna)').", no de {$identidad->base}.",
            ! is_array($registro['cambios'] ?? null) || $registro['cambios'] === [] => 'el registro no contiene cambios.',
            ($registro['plan_hash'] ?? null) !== $plan->hash() => 'el registro no corresponde al plan fijo actual.',
            ($errores = ReparacionZonaHoraria::erroresCambiosDelPlan($registro['cambios'], $plan)) !== [] => implode(' ', $errores),
            default => null,
        };
        if ($problema !== null) {
            $this->error("Rechazado: {$problema}");

            return self::FAILURE;
        }

        // Anexo de columnas originales: verificado por HMAC, vínculo con este registro y esquema actual.
        $columnas = null;
        $anexoSha256 = null;
        if ($this->option('anexo')) {
            try {
                $anexo = AnexoColumnas::leer((string) $this->option('anexo'));
            } catch (Throwable $e) {
                $this->error('Rechazado: '.$e->getMessage());

                return self::FAILURE;
            }
            if (($errores = AnexoColumnas::errores($anexo, (string) $this->option('manifiesto'), $registro)) !== []) {
                $this->error('Rechazado: '.implode(' ', $errores));

                return self::FAILURE;
            }
            $columnas = $anexo['columnas'];
            $anexoSha256 = hash_file('sha256', (string) $this->option('anexo'));
            $this->line('Huellas con las columnas originales del anexo ('.implode(', ', array_map(fn ($t, $c) => $t.': '.count($c), array_keys($columnas), $columnas)).').');
        }

        $cambios = $registro['cambios'];
        $this->info(($aplicar ? 'REVERSIÓN' : 'SIMULACIÓN DE REVERSIÓN (no modifica nada)')." | base: {$identidad->base} | ".count($cambios).' cambios | plan: '.$registro['plan_hash']);

        if (! $aplicar) {
            DB::beginTransaction();
            try {
                ReparacionZonaHoraria::prepararTransaccion(true);
                $errores = $reparacion->verificarReversion($plan, $cambios, $columnas);
            } finally {
                DB::rollBack();
            }
            $this->mostrarErrores($errores);
            $this->line($errores === [] ? 'Simulación correcta: se puede revertir. No se modificó ningún registro.' : 'La simulación encontró discrepancias: no se podría revertir.');

            return $errores === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($this->option('confirmar-plan') !== $registro['plan_hash']) {
            $this->error('Rechazado: --confirmar-plan no coincide con el plan del registro.');

            return self::FAILURE;
        }

        // Un registro nuevo por intento: nunca se sobrescribe el de una reversión anterior.
        $ruta = preg_replace('/\.json$/', '', (string) $this->option('manifiesto')).'-reversion-'.now('UTC')->format('Ymd_His').'.json';
        $reversion = [
            'tipo' => 'reversion', 'version' => 1, 'base' => $identidad->base, 'plan_hash' => $registro['plan_hash'],
            'registro_origen_sha256' => hash_file('sha256', (string) $this->option('manifiesto')), 'anexo_sha256' => $anexoSha256, 'inicio' => now('UTC')->toIso8601String(), 'estado' => 'pendiente', 'cambios' => [],
        ];

        try {
            RegistroCambios::crear($ruta, $reversion);
        } catch (Throwable $e) {
            $this->error('Rechazado: '.$e->getMessage());

            return self::FAILURE;
        }

        try {
            $revertidos = DB::transaction(function () use ($reparacion, $plan, $cambios, $registro, $identidad, $ruta, $reversion, $columnas) {
                ReparacionZonaHoraria::prepararTransaccion(false);
                if (ReparacionZonaHoraria::identidad()->base !== $identidad->base) {
                    throw new RuntimeException('La conexión ya no corresponde a la base '.$identidad->base.'.');
                }

                $revertidos = $reparacion->revertir($plan, $cambios, 'zona-horaria:revertir plan:'.substr($registro['plan_hash'], 0, 12), $columnas);
                RegistroCambios::guardar($ruta, [...$reversion, 'estado' => 'confirmando', 'cambios' => $revertidos]);

                return $revertidos;
            });
        } catch (Throwable $e) {
            RegistroCambios::guardar($ruta, [...$reversion, 'estado' => 'fallido', 'fin' => now('UTC')->toIso8601String(), 'error' => $e->getMessage()]);
            if ($e instanceof DiscrepanciaException) {
                $this->mostrarErrores($e->errores);
            }
            $this->error('Abortado sin modificar registros: '.$e->getMessage());

            return self::FAILURE;
        }

        RegistroCambios::guardar($ruta, [...$reversion, 'estado' => 'aplicado', 'fin' => now('UTC')->toIso8601String(), 'cambios' => $revertidos]);
        $this->info(count($revertidos).' fechas restauradas a su valor original. Registro: '.$ruta);

        return self::SUCCESS;
    }

    private function mostrarErrores(array $errores): void
    {
        foreach ($errores as $clave => $lista) {
            foreach ($lista as $error) {
                $this->warn("  {$clave}: {$error}");
            }
        }
    }
}
