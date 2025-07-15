<?php

namespace App\Policies;

use App\Models\User;
use App\Models\State;
use Illuminate\Auth\Access\HandlesAuthorization;

class StatePolicy extends BasePolicy
{
    protected static string $tableName = 'state';
}
