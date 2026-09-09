<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function view(User $administrator, User $user): bool
    {
        return $this->isActiveAdministrator($administrator);
    }

    public function update(User $administrator, User $user): bool
    {
        return $this->isActiveAdministrator($administrator);
    }

    public function updateStatus(User $administrator, User $user): bool
    {
        return $this->isActiveAdministrator($administrator)
            && $user->hasRole('ciudadano');
    }

    private function isActiveAdministrator(User $user): bool
    {
        return $user->estado && $user->hasRole('administrador');
    }
}
