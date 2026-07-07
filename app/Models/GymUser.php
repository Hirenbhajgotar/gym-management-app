<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property string $role
 * @property bool $active
 */
class GymUser extends Pivot
{
    use HasFactory;
    //
}
