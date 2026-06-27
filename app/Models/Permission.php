<?php

namespace App\Models;

use App\Enums\PermissionCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission as ModelsPermission;
use Spatie\Permission\Models\Role;

/**
 * @property int $id
 * @property string $name
 * @property string|null $display_name
 * @property string|null $description
 * @property PermissionCategory|null $category
 * @property string $guard_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static Builder<static>|Permission newModelQuery()
 * @method static Builder<static>|Permission newQuery()
 * @method static Builder<static>|Permission permission($permissions, $without = false)
 * @method static Builder<static>|Permission query()
 * @method static Builder<static>|Permission role($roles, $guard = null, $without = false)
 * @method static Builder<static>|Permission whereCategory($value)
 * @method static Builder<static>|Permission whereCreatedAt($value)
 * @method static Builder<static>|Permission whereDescription($value)
 * @method static Builder<static>|Permission whereDisplayName($value)
 * @method static Builder<static>|Permission whereGuardName($value)
 * @method static Builder<static>|Permission whereId($value)
 * @method static Builder<static>|Permission whereName($value)
 * @method static Builder<static>|Permission whereUpdatedAt($value)
 * @method static Builder<static>|Permission withoutPermission($permissions)
 * @method static Builder<static>|Permission withoutRole($roles, $guard = null)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperPermission
 */
class Permission extends ModelsPermission
{
    use HasFactory;

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
