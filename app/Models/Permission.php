<?php

namespace App\Models;

use App\Enums\PermissionCategory;
use Spatie\Permission\Models\Permission as ModelsPermission;

class Permission extends ModelsPermission
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => PermissionCategory::class,
        ];
    }
}
