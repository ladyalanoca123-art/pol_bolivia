<?php

namespace App\Policies;

use App\Models\PostulacionVoluntariado;
use App\Models\User;

class PostulacionVoluntariadoPolicy
{
    public function approve(User $user, PostulacionVoluntariado $postulacion): bool
    {
        return $this->isActiveAdministrator($user) && $postulacion->estado === 'pendiente';
    }

    public function reject(User $user, PostulacionVoluntariado $postulacion): bool
    {
        return $this->approve($user, $postulacion);
    }

    private function isActiveAdministrator(User $user): bool
    {
        return $user->estado && $user->hasRole('administrador');
    }
}
