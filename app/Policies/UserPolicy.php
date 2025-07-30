<?php

namespace App\Policies;

use App\Enums\DefaultRole;
use App\Models\User;

use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy extends BasePolicy
{
    protected static string $tableName = 'user';

    /**
     * Perform pre-authorization checks on the model.
     */
    // public function before(User $user, string $ability): ?bool
    // {
    //     if ($user->hasAnyRole(DefaultRole::globalRoles())) {
    //         return true;
    //     }

    //     return null; // see the note above in Gate::before about why null must be returned here.
    // }
}
