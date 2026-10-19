<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function view(User $administrator, User $user): bool
    {
        return $this->isActiveAdministrator($administrator);
    }

    /** Al administrador principal solo lo edita él mismo. */
    public function update(User $administrator, User $user): bool
    {
        return $this->isActiveAdministrator($administrator)
            && $this->isNotOthersPrincipal($administrator, $user);
    }

    /**
     * Cambiar el correo: no el de otro administrador, porque con la recuperación de contraseña
     * permitiría apropiarse de su cuenta. El propio sí, y el de ciudadanos y voluntarios.
     */
    public function updateEmail(User $administrator, User $user): bool
    {
        return $this->isActiveAdministrator($administrator)
            && $this->isNotOthersPrincipal($administrator, $user)
            && (! $user->hasRole('administrador') || $administrator->is($user));
    }

    public function updateStatus(User $administrator, User $user): bool
    {
        return $this->isActiveAdministrator($administrator)
            && ! $user->es_principal
            && $user->hasRole('ciudadano');
    }

    /**
     * Eliminar la propia cuenta desde el perfil: solo un ciudadano activo y solo la suya.
     * Un administrador no (así nunca se elimina el último ni el principal) ni un voluntario (su historial se conserva).
     */
    public function deleteOwnAccount(User $actor, User $user): bool
    {
        return $actor->estado
            && ! $actor->es_principal
            && $actor->hasRole('ciudadano')
            && $actor->is($user);
    }

    private function isActiveAdministrator(User $user): bool
    {
        return $user->estado && $user->hasRole('administrador');
    }

    /** El administrador principal está protegido frente a los demás administradores (también en PostgreSQL). */
    private function isNotOthersPrincipal(User $administrator, User $user): bool
    {
        return ! $user->es_principal || $administrator->is($user);
    }
}
