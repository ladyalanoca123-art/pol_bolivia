<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\EjecutorConsola;
use App\Support\RegistroSeguridad;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pasa la marca de administrador principal (usuarios.es_principal) a otro administrador activo. El principal
 * anterior sigue siendo administrador normal. Si todavía no hay principal, simplemente lo designa.
 * En una transacción con las filas bloqueadas (lockForUpdate); PostgreSQL garantiza además que solo haya uno
 * (índice único parcial) y que sea un administrador activo (trigger_proteger_administrador_principal).
 * Queda en la auditoría y en el log "seguridad" (administrador_principal), con quién lo ejecutó.
 */
class TransferirPrincipal extends Command
{
    use Concerns\GestionaAdministradores;

    protected $signature = 'pol:transferir-principal
        {correo : Correo del administrador activo que será el nuevo administrador principal}
        {--force : No pide confirmación}';

    protected $description = 'Transfiere el rol de administrador principal a otro administrador activo';

    public function handle(RegistroSeguridad $registro): int
    {
        $destinatario = $this->buscarUsuario((string) $this->argument('correo'));

        if ($destinatario === null) {
            $this->error('No existe ninguna cuenta con ese correo.');

            return self::FAILURE;
        }

        if (! $destinatario->hasRole('administrador') || ! $destinatario->estado) {
            $this->error("{$destinatario->email} debe ser un administrador con la cuenta activa; no se hizo ningún cambio. Si hace falta, promuévelo antes con pol:promover-administrador.");

            return self::FAILURE;
        }

        if ($destinatario->es_principal) {
            $this->warn("{$destinatario->email} ya es el administrador principal; no se hizo ningún cambio.");

            return self::SUCCESS;
        }

        $anterior = User::query()->where('es_principal', true)->first();

        $this->mostrarUsuario($destinatario);
        $this->line($anterior === null
            ? 'Todavía no hay ningún administrador principal.'
            : "Administrador principal actual: {$anterior->email} (seguirá siendo administrador)."
        );

        if (! $this->confirmar("¿Transferir el rol de administrador principal a {$destinatario->email}?")) {
            $this->warn('Cancelado: no se hizo ningún cambio.');

            return self::FAILURE;
        }

        $resultado = EjecutorConsola::durante(fn () => DB::transaction(function () use ($destinatario): array {
            // Bloquea al principal actual y al destinatario (siempre en el mismo orden) antes de volver a comprobar.
            $filas = User::query()->where('es_principal', true)->orWhere('id', $destinatario->id)->orderBy('id')->lockForUpdate()->get();
            $nuevo = $filas->firstWhere('id', $destinatario->id);
            $anterior = $filas->first(fn (User $fila) => $fila->es_principal && ! $fila->is($destinatario));

            if ($nuevo === null || ! $nuevo->hasRole('administrador') || ! $nuevo->estado || $nuevo->es_principal) {
                return ['transferido' => false, 'anterior' => null];
            }

            // Primero se quita la marca al anterior: el índice único no admite dos principales a la vez.
            $anterior?->forceFill(['es_principal' => false])->save();
            $nuevo->forceFill(['es_principal' => true])->save();

            return ['transferido' => true, 'anterior' => $anterior];
        }));

        if (! $resultado['transferido']) {
            $this->error('Las cuentas cambiaron mientras se confirmaba; no se hizo ningún cambio. Vuelve a ejecutar el comando.');

            return self::FAILURE;
        }

        $registro->administradorPrincipal($destinatario, $resultado['anterior']);
        $this->info("{$destinatario->email} es ahora el administrador principal."
            .($resultado['anterior'] ? " {$resultado['anterior']->email} sigue siendo administrador." : ''));

        return self::SUCCESS;
    }
}
