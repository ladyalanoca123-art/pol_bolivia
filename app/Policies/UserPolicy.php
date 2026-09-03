<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function updateStatus(User $administrator, User $user): bool
    {
        return $administrator->estado
            && $administrator->hasRole('administrador')
            && $user->hasRole('ciudadano');
    }
}
