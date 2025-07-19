<?php

namespace App\Models;

use App\Enums\PermissionCategory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission as ModelsPermission;
use Illuminate\Support\Str;

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
