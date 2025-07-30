<?php

namespace App\Services;

use App\Enums\DefaultRole;
use App\Models\Gym;
use App\Models\PermissionSetting;
use App\Models\User;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsService
{
    /**
     * Create default roles for a gym tenant.
     *
     * @param User|null $user
     * @param Gym|null $tenant
     * @return void
     */
    public function createDefaultTenantRolesAndAssignPermissions(?User $user = null, ?Gym $tenant = null): void
    {
        if (! $user || ! $tenant) {
            return;
        }

        // Get all tenant-specific roles
        $roles = DefaultRole::tenantRoles();

        $roleData = array_map(fn($role) => [
            'name'         => $role->value,
            'guard_name'   => 'web',
            'gym_id'       => $tenant->id,
            'created_at'   => now(),
            'updated_at'   => now(),
        ], $roles);

        // Use bulk insert to minimize DB queries
        Role::insert($roleData);

        // Now assign permissions to each role using PermissionSetting
        foreach ($roles as $role) {
            $permissionSetting = PermissionSetting::where('role', $role->value)->first();

            if ($permissionSetting && ! empty($permissionSetting->default_permissions)) {
                $roleModel = Role::where('name', $role->value)->where('gym_id', $tenant->id)->first();

                // Sync permissions (avoids duplicates)
                $roleModel->syncPermissions($permissionSetting->default_permissions);
            }
        }
    }
}
