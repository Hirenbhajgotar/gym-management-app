<?php

namespace App\Models;

use App\Casts\DefaultResourcesCast;
use Illuminate\Database\Eloquent\Model;

class PermissionSetting extends Model
{
    protected $fillable = [
        'role',
        'default_resources',
        'default_permissions',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_resources' => DefaultResourcesCast::class,
            'default_permissions' => 'array',
        ];
    }
}
