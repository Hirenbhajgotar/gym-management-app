<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\DefaultResourcesCast;
use App\Enums\DefaultRole;
use Database\Factories\PermissionSettingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @deprecated No longer used by the permission management UI. The UI now auto-discovers
 *             Resources, Pages, and Widgets via {@see \App\Services\PermissionDiscoveryService}.
 *             This model is retained only for backward-compatible seeding via
 *             {@see \App\Services\RolesAndPermissionsService}. Will be removed once seeding
 *             switches to the new discovery service.
 *
 * @property int $id
 * @property DefaultRole|null $role
 * @property mixed|null $default_resources
 * @property array<array-key, mixed>|null $default_permissions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static PermissionSettingFactory factory($count = null, $state = [])
 * @method static Builder<static>|PermissionSetting newModelQuery()
 * @method static Builder<static>|PermissionSetting newQuery()
 * @method static Builder<static>|PermissionSetting query()
 * @method static Builder<static>|PermissionSetting whereCreatedAt($value)
 * @method static Builder<static>|PermissionSetting whereDefaultPermissions($value)
 * @method static Builder<static>|PermissionSetting whereDefaultResources($value)
 * @method static Builder<static>|PermissionSetting whereId($value)
 * @method static Builder<static>|PermissionSetting whereRole($value)
 * @method static Builder<static>|PermissionSetting whereUpdatedAt($value)
 *
 * @mixin \Eloquent
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
