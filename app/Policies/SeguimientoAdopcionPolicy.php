<?php

namespace App\Policies;

use App\Models\SeguimientoAdopcion;
use App\Models\User;

class SeguimientoAdopcionPolicy
{
    public function view(User $user, SeguimientoAdopcion $seguimiento): bool
    {
        return $this->isAdmin($user) || $seguimiento->voluntario_id === $user->id;
    }

    public function assign(User $user, SeguimientoAdopcion $seguimiento): bool
    {
        return $this->isAdmin($user) && $seguimiento->estado === 'sin_asignar';
    }

    public function close(User $user, SeguimientoAdopcion $seguimiento): bool
    {
        return $this->isAdmin($user) && $seguimiento->estado === 'activo';
    }

    public function work(User $user, SeguimientoAdopcion $seguimiento): bool
    {
        return $user->estado
            && $user->hasRole('voluntario')
            && $seguimiento->estado === 'activo'
            && $seguimiento->voluntario_id === $user->id;
    }

    private function isAdmin(User $user): bool
    {
        return $user->estado && $user->hasRole('administrador');
    }
}
