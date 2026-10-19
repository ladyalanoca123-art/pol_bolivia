<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\RegistroSeguridad;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Quita el rol de administrador a una cuenta (pasa a ciudadano). Nunca deja el sistema sin administradores activos:
 * dentro de la transacción se bloquean las filas de los administradores activos (lockForUpdate), así dos ejecuciones
 * simultáneas no pueden retirar a los dos últimos. Cierra sus sesiones guardadas y anula su «Recordarme».
 * Queda en la auditoría (cambio de rol) y en el log "seguridad" (rol_administrador, accion=retirado).
 */
class RetirarAdministrador extends Command
{
    use Concerns\GestionaAdministradores;

    /** Roles a los que se puede retirar: "voluntario" exige una postulación aprobada y su perfil, así que no. */
    private const ROLES_DESTINO = ['ciudadano'];

    protected $signature = 'pol:retirar-administrador
        {correo : Correo del administrador que deja de serlo}
        {--rol=ciudadano : Rol que tendrá la cuenta (solo ciudadano)}
        {--force : No pide confirmación}';

    protected $description = 'Quita el rol de administrador a una cuenta, sin dejar el sistema sin administradores activos';

    public function handle(RegistroSeguridad $registro): int
    {
        $rolNuevo = (string) $this->option('rol');

        if (! in_array($rolNuevo, self::ROLES_DESTINO, true)) {
            $this->error('El rol nuevo solo puede ser «ciudadano». Para ser voluntario, la persona debe postularse y ser aprobada.');

            return self::FAILURE;
        }

        $usuario = $this->buscarUsuario((string) $this->argument('correo'));

        if ($usuario === null) {
            $this->error('No existe ninguna cuenta con ese correo.');

            return self::FAILURE;
        }

        if (! $usuario->hasRole('administrador')) {
            $this->error("{$usuario->email} no es administrador; no se hizo ningún cambio.");

            return self::FAILURE;
        }

        $this->mostrarUsuario($usuario);

        if (! $this->confirmar("¿Quitar el rol de administrador a {$usuario->email}? Pasará a ser {$rolNuevo}.")) {
            $this->warn('Cancelado: no se hizo ningún cambio.');

            return self::FAILURE;
        }

        $resultado = DB::transaction(function () use ($usuario, $rolNuevo): string {
            // Bloquea a todos los administradores activos (siempre en el mismo orden) antes de contar.
            $activos = User::query()->where('rol', 'administrador')->where('estado', true)->orderBy('id')->lockForUpdate()->get();
            $actual = User::query()->whereKey($usuario->id)->lockForUpdate()->first();

            if ($actual === null || ! $actual->hasRole('administrador')) {
                return 'ya_no_es';
            }

            if ($activos->reject(fn (User $activo) => $activo->is($actual))->isEmpty()) {
                return 'ultimo';
            }

            $actual->forceFill(['rol' => $rolNuevo, 'remember_token' => null])->save();

            // Sesiones guardadas en la base (SESSION_DRIVER=database): se cierran todas las de esa cuenta.
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $actual->id)->delete();
            }

            return 'retirado';
        });

        if ($resultado === 'ultimo') {
            $this->error("No se puede retirar a {$usuario->email}: es el último administrador activo y el sistema no puede quedar sin administradores. Promueve antes a otra persona.");

            return self::FAILURE;
        }

        if ($resultado === 'ya_no_es') {
            $this->error('La cuenta cambió mientras se confirmaba (ya no es administrador); no se hizo ningún cambio.');

            return self::FAILURE;
        }

        $registro->rolAdministrador($usuario, 'retirado', 'administrador', $rolNuevo);
        $this->info("{$usuario->email} ya no es administrador (ahora es {$rolNuevo}). Pierde el acceso al panel de inmediato.");

        return self::SUCCESS;
    }
}
