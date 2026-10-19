<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\EjecutorConsola;
use App\Support\RegistroSeguridad;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Convierte en administradora una cuenta de ciudadano activa. Solo por consola: la web no permite cambiar roles.
 * La persona se registra antes por su cuenta y entra con su propia contraseña; este comando nunca la pide ni la cambia.
 * Queda en la auditoría (cambio de rol) y en el log "seguridad" (rol_administrador, accion=promovido), con quién
 * lo ejecutó (usuario del sistema y equipo, App\Support\EjecutorConsola).
 */
class PromoverAdministrador extends Command
{
    use Concerns\GestionaAdministradores;

    protected $signature = 'pol:promover-administrador
        {correo : Correo de la cuenta (ya registrada) que pasará a ser administradora}
        {--force : No pide confirmación}';

    protected $description = 'Convierte en administradora una cuenta de ciudadano activa (no toca contraseñas)';

    public function handle(RegistroSeguridad $registro): int
    {
        $usuario = $this->buscarUsuario((string) $this->argument('correo'));

        if ($usuario === null) {
            $this->error('No existe ninguna cuenta con ese correo. La persona debe registrarse primero en POL-Bolivia.');

            return self::FAILURE;
        }

        if ($usuario->hasRole('administrador')) {
            $this->warn("{$usuario->email} ya es administrador; no se hizo ningún cambio.");

            return self::SUCCESS;
        }

        if (! $usuario->estado) {
            $this->error("La cuenta {$usuario->email} está desactivada; no se puede promover. Reactívala primero desde «Usuarios».");

            return self::FAILURE;
        }

        if (! $usuario->hasRole('ciudadano')) {
            $this->error($this->motivoVoluntario($usuario));

            return self::FAILURE;
        }

        $this->mostrarUsuario($usuario);

        if (! $this->confirmar("¿Convertir a {$usuario->email} en administrador?")) {
            $this->warn('Cancelado: no se hizo ningún cambio.');

            return self::FAILURE;
        }

        // Con la fila bloqueada se vuelve a comprobar: pudo cambiar mientras se pedía la confirmación.
        $promovido = EjecutorConsola::durante(fn () => DB::transaction(function () use ($usuario): bool {
            $actual = User::query()->whereKey($usuario->id)->lockForUpdate()->first();

            if ($actual === null || ! $actual->estado || ! $actual->hasRole('ciudadano')) {
                return false;
            }

            $actual->forceFill(['rol' => 'administrador'])->save();

            return true;
        }));

        if (! $promovido) {
            $this->error('La cuenta cambió mientras se confirmaba (ya no es un ciudadano activo); no se hizo ningún cambio.');

            return self::FAILURE;
        }

        $registro->rolAdministrador($usuario, 'promovido', 'ciudadano', 'administrador');
        $this->info("{$usuario->email} ahora es administrador. Entra con su propia contraseña y verá el panel de administración.");

        return self::SUCCESS;
    }

    /**
     * Un voluntario no se promueve directamente: PostgreSQL (trigger_proteger_voluntario_asignado) impide cambiar
     * el rol mientras tenga un perfil de voluntario activo o seguimientos activos. Primero se le da de baja desde
     * «Voluntarios» (así vuelve a ser ciudadano) y después se promueve.
     */
    private function motivoVoluntario(User $usuario): string
    {
        $seguimientos = $usuario->seguimientosAsignados()->where('estado', 'activo')->count();
        $visitas = $usuario->visitasSeguimiento()->where('estado', 'programada')->count();

        $mensaje = "Solo se pueden promover ciudadanos y {$usuario->email} es voluntario. "
            .'Primero hay que darlo de baja desde «Voluntarios» (vuelve a ser ciudadano) y después promoverlo.';

        if ($seguimientos > 0 || $visitas > 0) {
            $mensaje .= " Tiene {$seguimientos} seguimiento(s) activo(s) y {$visitas} visita(s) programada(s):"
                .' antes de la baja hay que reasignarlos o cerrarlos, para no dejar casos sin responsable.';
        }

        return $mensaje;
    }
}
