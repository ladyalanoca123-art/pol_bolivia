<?php

namespace App\Policies;

use App\Models\SolicitudAdopcion;
use App\Models\User;

class SolicitudAdopcionPolicy
{
    public function accept(User $user, SolicitudAdopcion $solicitud): bool
    {
        return $this->isActiveAdministrator($user)
            && $this->hasPendingDecision($solicitud)
            && $solicitud->mascota->estado === 'disponible';
    }

    public function reject(User $user, SolicitudAdopcion $solicitud): bool
    {
        return $this->isActiveAdministrator($user)
            && $this->hasPendingDecision($solicitud);
    }

    private function isActiveAdministrator(User $user): bool
    {
        return $user->estado && $user->hasRole('administrador');
    }

    private function hasPendingDecision(SolicitudAdopcion $solicitud): bool
    {
        return in_array($solicitud->estado, ['pendiente', 'en_espera'], true);
    }
}
