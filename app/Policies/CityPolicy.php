<?php

namespace App\Policies;

use App\Models\User;
use App\Models\City;
use Illuminate\Auth\Access\HandlesAuthorization;

class CityPolicy extends BasePolicy
{
    protected static string $tableName = 'city';
}
