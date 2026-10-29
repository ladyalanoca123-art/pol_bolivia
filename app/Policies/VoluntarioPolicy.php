<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Voluntario;

class VoluntarioPolicy
{
    public function deactivate(User $user, Voluntario $voluntario): bool
    {
        return $user->estado
            && $user->hasRole('administrador')
            && $voluntario->estado === 'activo'
            && ! $voluntario->usuario->seguimientosAsignados()->where('estado', 'activo')->exists();
    }
}
