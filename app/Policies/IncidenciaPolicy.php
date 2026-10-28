<?php

namespace App\Policies;

use App\Models\Incidencia;
use App\Models\User;

class IncidenciaPolicy
{
    public function progress(User $user, Incidencia $incidencia): bool
    {
        return $user->estado
            && $user->hasRole('administrador')
            && $incidencia->estado === 'pendiente';
    }

    /**
     * Se puede atender directamente desde «Pendiente»: marcar «En proceso» es opcional (solo avisa al equipo que
     * alguien ya se ocupa). PostgreSQL exige igual el comentario, el responsable y la fecha de atención.
     */
    public function attend(User $user, Incidencia $incidencia): bool
    {
        if (! $user->estado || ! in_array($incidencia->estado, ['pendiente', 'en_proceso'], true)) {
            return false;
        }

        return $user->hasRole('administrador')
            || ($user->hasRole('voluntario') && $incidencia->seguimiento->voluntario_id === $user->id);
    }
}
