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

    public function attend(User $user, Incidencia $incidencia): bool
    {
        if (! $user->estado || $incidencia->estado !== 'en_proceso') {
            return false;
        }

        return $user->hasRole('administrador')
            || ($user->hasRole('voluntario') && $incidencia->seguimiento->voluntario_id === $user->id);
    }
}
