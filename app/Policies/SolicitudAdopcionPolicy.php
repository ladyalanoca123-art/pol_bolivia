<?php

namespace App\Policies;

use App\Models\SolicitudAdopcion;
use App\Models\User;

/**
 * Flujo de la solicitud de adopción (PostgreSQL lo exige además en validar_cambio_estado_solicitud):
 *
 *   pendiente «Recibida»
 *     → (evaluate) en_espera «En evaluación»: la administración revisa el formulario y contacta externamente
 *     → (approve)  aprobada «Reservada»: la mascota queda reservada y se asigna el número de acta
 *     → (deliver)  aceptada «Adopción concretada»: exige el compromiso firmado y la fecha real de entrega
 *
 *   pendiente o en_espera → (reject) rechazada, con motivo
 *   aprobada → (markNotDelivered) no_concretada, con motivo: la mascota vuelve a estar disponible
 *   pendiente, en_espera o aprobada → (cancel) cancelada por el propio solicitante
 *
 * Todas las acciones del equipo son solo para administradores activos. El contacto por WhatsApp o llamada
 * (contact) solo se ofrece a la administración mientras la solicitud está en evaluación o aprobada.
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

    public function approve(User $user, SolicitudAdopcion $solicitud): bool
    {
        return $this->isActiveAdministrator($user)
            && $solicitud->estado === 'en_espera'
            && $solicitud->mascota->estado === 'disponible';
    }

    /** El equipo autorizado puede generar el formato y consultar su evidencia en una solicitud aprobada o concretada. */
    public function viewCommitment(User $user, SolicitudAdopcion $solicitud): bool
    {
        return $this->isActiveAdministrator($user)
            && in_array($solicitud->estado, ['aprobada', 'aceptada'], true);
    }

    /** La evidencia puede consultarse por administración mientras se conserve en la solicitud. */
    public function viewCommitmentEvidence(User $user, SolicitudAdopcion $solicitud): bool
    {
        return $this->isActiveAdministrator($user)
            && filled($solicitud->compromiso_foto_path);
    }

    /** La evidencia solo se registra mientras la entrega está pendiente; adjuntarla no confirma la firma. */
    public function uploadCommitmentEvidence(User $user, SolicitudAdopcion $solicitud): bool
    {
        return $this->isActiveAdministrator($user)
            && $solicitud->estado === 'aprobada';
    }

    /** Registrar entrega: aprobada → Adopción concretada. */
    public function deliver(User $user, SolicitudAdopcion $solicitud): bool
    {
        return $this->isActiveAdministrator($user)
            && $solicitud->estado === 'aprobada'
            && $solicitud->mascota->estado === 'reservada';
    }

    public function markNotDelivered(User $user, SolicitudAdopcion $solicitud): bool
    {
        return $this->isActiveAdministrator($user)
            && $solicitud->estado === 'aprobada';
    }

    public function reject(User $user, SolicitudAdopcion $solicitud): bool
    {
        return $this->isActiveAdministrator($user)
            && $this->hasPendingDecision($solicitud);
    }

    /** Botones «Escribir por WhatsApp» y «Llamar» junto a los datos del solicitante. */
    public function contact(User $user, SolicitudAdopcion $solicitud): bool
    {
        return $this->isActiveAdministrator($user)
            && in_array($solicitud->estado, ['en_espera', 'aprobada'], true);
    }

    /** El propio solicitante cancela su solicitud mientras está en trámite. */
    public function cancel(User $user, SolicitudAdopcion $solicitud): bool
    {
        return $user->estado
            && $solicitud->solicitante_id === $user->id
            && $solicitud->estaActiva();
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
