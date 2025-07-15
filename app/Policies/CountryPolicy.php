<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Country;
use Illuminate\Auth\Access\HandlesAuthorization;

class CountryPolicy extends BasePolicy
{
    protected static string $tableName = 'country';
}
