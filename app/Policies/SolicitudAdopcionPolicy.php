<?php

namespace App\Policies;

use App\Models\SolicitudAdopcion;
use App\Models\User;

/**
 * Flujo de decisión del administrador:
 *   pendiente  → (evaluar) en_espera "En evaluación" → (aceptar) aceptada
 *   pendiente o en_espera → (rechazar) rechazada
 *   pendiente o en_espera → (solicitar información) mismo estado + motivo
 *
 * Solo se acepta desde "En evaluación": la mascota no se adopta sin entrevista o visita previa.
 */
class SolicitudAdopcionPolicy
{
    public function evaluate(User $user, SolicitudAdopcion $solicitud): bool
    {
        return $this->isActiveAdministrator($user)
            && $solicitud->estado === 'pendiente';
    }

    public function requestInformation(User $user, SolicitudAdopcion $solicitud): bool
    {
        return $this->isActiveAdministrator($user)
            && $this->hasPendingDecision($solicitud);
    }

    public function accept(User $user, SolicitudAdopcion $solicitud): bool
    {
        return $this->isActiveAdministrator($user)
            && $solicitud->estado === 'en_espera'
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
