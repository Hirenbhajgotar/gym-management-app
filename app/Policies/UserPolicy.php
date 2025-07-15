<?php

namespace App\Policies;

use App\Models\User;

use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy extends BasePolicy
{
    protected static string $tableName = 'user';
}
