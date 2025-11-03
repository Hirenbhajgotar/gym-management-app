<?php

namespace App\Models;

use App\Casts\DefaultResourcesCast;
use App\Enums\DefaultRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property DefaultRole|null $role
 * @property mixed|null $default_resources
 * @property array<array-key, mixed>|null $default_permissions
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\PermissionSettingFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionSetting whereDefaultPermissions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionSetting whereDefaultResources($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionSetting whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionSetting whereUpdatedAt($value)
 * @mixin \Eloquent
 * @mixin IdeHelperPermissionSetting
 */
class PermissionSetting extends Model
{
    use HasFactory;

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
            'role' => DefaultRole::class,
        ];
    }
}
