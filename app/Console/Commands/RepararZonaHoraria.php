<?php

namespace App\Console\Commands;

use App\Support\ZonaHoraria\DiscrepanciaException;
use App\Support\ZonaHoraria\PlanReparacion;
use App\Support\ZonaHoraria\RegistroCambios;
use App\Support\ZonaHoraria\ReparacionZonaHoraria;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Reparación del desfase de +4 h en las fechas confirmadas (PlanReparacion).
 *
 * Por defecto SIMULA (transacción de solo lectura). Para modificar datos exige a la vez:
 * --aplicar, --base=<base conectada>, --confirmar-plan=<hash del plan> y una base autorizada
 * (marca de la base; en pol_bolivia, --confirmar-base=pol_bolivia).
 */
class RepararZonaHoraria extends Command
{
    protected $signature = 'zona-horaria:reparar
        {--base= : Nombre de la base conectada (obligatorio)}
        {--aplicar : Aplica los cambios (sin esta opción solo simula)}
        {--confirmar-plan= : Hash SHA-256 del plan, mostrado por la simulación}
        {--confirmar-base= : Confirmación adicional para la base sin marca (pol_bolivia)}
        {--manifiesto= : Ruta del registro de cambios (por defecto storage/app/private/zona-horaria)}';

    protected $description = 'Corrige (−4 h) solo las fechas confirmadas del plan; simula por defecto';

    public function handle(ReparacionZonaHoraria $reparacion): int
    {
        $plan = $this->laravel->make(PlanReparacion::class);
        $aplicar = (bool) $this->option('aplicar');
        $identidad = ReparacionZonaHoraria::identidad();

        $motivo = ReparacionZonaHoraria::motivoRechazoBase($identidad->base, $identidad->marca, $this->option('base'), $this->option('confirmar-base'), $aplicar);
        if ($motivo !== null) {
            $this->error("Rechazado: {$motivo}");

            return self::FAILURE;
        }

        $this->info(($aplicar ? 'APLICACIÓN' : 'SIMULACIÓN (no modifica nada)')." | base: {$identidad->base} | marca: ".($identidad->marca ?? '(ninguna)')
            .' | Laravel: '.config('app.timezone').' | sesión: '.DB::selectOne('show timezone')->TimeZone);
        $this->line('Plan: '.count($plan->entradas()).' entradas, '.count($plan->exclusiones()).' exclusiones | hash: '.$plan->hash());

        if (! $aplicar) {
            return $this->simular($reparacion, $plan);
        }

        if ($this->option('confirmar-plan') !== $plan->hash()) {
            $this->error('Rechazado: --confirmar-plan no coincide con el hash del plan.');

            return self::FAILURE;
        }

        $ruta = $this->option('manifiesto') ?: RegistroCambios::rutaPorDefecto('reparacion', $identidad->base);
        $registro = [
            'tipo' => 'reparacion', 'version' => 1, 'base' => $identidad->base, 'plan_hash' => $plan->hash(),
            'zona_laravel' => config('app.timezone'), 'zona_sesion' => DB::selectOne('show timezone')->TimeZone,
            'inicio' => now('UTC')->toIso8601String(), 'estado' => 'pendiente', 'cambios' => [],
        ];
        try {
            RegistroCambios::crear($ruta, $registro);
        } catch (Throwable $e) {
            $this->error('Rechazado: '.$e->getMessage());

            return self::FAILURE;
        }

        $preparados = [];
        try {
            $cambios = DB::transaction(function () use ($reparacion, $plan, $identidad, $ruta, $registro, &$preparados) {
                ReparacionZonaHoraria::prepararTransaccion(false);
                // La conexión de la transacción debe seguir siendo la base autorizada.
                if (ReparacionZonaHoraria::identidad()->base !== $identidad->base) {
                    throw new RuntimeException('La conexión ya no corresponde a la base '.$identidad->base.'.');
                }

                $preparados = $reparacion->aplicar($plan, 'zona-horaria:reparar plan:'.substr($plan->hash(), 0, 12));
                // Antes de confirmar: si el proceso se interrumpe tras el COMMIT, el registro ya
                // contiene los valores originales y permite revertir.
                RegistroCambios::guardar($ruta, [...$registro, 'estado' => 'confirmando', 'cambios' => $preparados]);

                return $preparados;
            });
        } catch (Throwable $e) {
            RegistroCambios::guardar($ruta, [...$registro, 'estado' => 'fallido', 'fin' => now('UTC')->toIso8601String(), 'error' => $e->getMessage(), 'cambios_no_confirmados' => $preparados]);

            return $this->fallo($e, $ruta);
        }

        RegistroCambios::guardar($ruta, [...$registro, 'estado' => 'aplicado', 'fin' => now('UTC')->toIso8601String(), 'cambios' => $cambios]);

        $this->table(['Columna', 'ID', 'Original (UTC)', 'Nuevo (UTC)'], array_map(fn ($c) => ["{$c['tabla']}.{$c['columna']}", $c['id'], $c['original'], $c['nuevo']], $cambios));
        $this->info(count($cambios).' fechas corregidas. Registro de cambios: '.$ruta);

        return self::SUCCESS;
    }

    private function simular(ReparacionZonaHoraria $reparacion, PlanReparacion $plan): int
    {
        DB::beginTransaction();
        try {
            ReparacionZonaHoraria::prepararTransaccion(true);
            $errores = $reparacion->verificar($plan);
            $triggers = ReparacionZonaHoraria::estadoTriggers();
            $problemas = ReparacionZonaHoraria::problemasTriggers();
        } finally {
            DB::rollBack();
        }

        $this->table(['Columna', 'ID', 'Actual (UTC)', 'Nuevo (UTC)', 'Evidencia', 'Verificación'], array_map(fn ($e) => [
            "{$e['tabla']}.{$e['columna']}", $e['id'], $e['actual'], $e['nuevo'],
            'auditoría #'.$e['evidencia']['auditoria_id'],
            isset($errores[PlanReparacion::clave($e)]) ? 'DISCREPANCIA' : 'ok',
        ], $plan->entradas()));

        $inactivos = array_keys(array_filter($triggers, fn ($t) => $t !== 'O'));
        $this->line('Triggers de updated_at: '.($inactivos === [] ? 'los 8 activos' : 'NO activos: '.implode(', ', $inactivos)));

        if ($errores !== [] || $problemas !== []) {
            $this->mostrarErrores($errores);
            foreach ($problemas as $problema) {
                $this->warn("  {$problema}");
            }
            $this->error('La simulación encontró problemas: no se podría aplicar.');

            return self::FAILURE;
        }

        $this->info('Simulación correcta: todas las entradas coinciden. No se modificó ningún registro.');
        $this->line('Para aplicar: --aplicar --base=<base> --confirmar-plan='.$plan->hash());

        return self::SUCCESS;
    }

    private function fallo(Throwable $e, string $ruta): int
    {
        if ($e instanceof DiscrepanciaException) {
            $this->mostrarErrores($e->errores);
        }
        $this->error('Abortado sin modificar registros: '.$e->getMessage());

        $inactivos = array_keys(array_filter(ReparacionZonaHoraria::estadoTriggers(), fn ($t) => $t !== 'O'));
        $this->line('Triggers de updated_at tras deshacer: '.($inactivos === [] ? 'los 8 activos' : 'NO activos: '.implode(', ', $inactivos)));
        $this->line('Registro del intento: '.$ruta);

        return self::FAILURE;
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
