<?php

namespace App\Services;

use App\Enums\DefaultRole;
use App\Enums\PermissionCategory;
use App\Models\Gym;
use App\Models\Permission;
use App\Models\PermissionSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsService
{
    /**
     * Create default roles for a gym tenant.
     */
    public function createDefaultTenantRolesAndAssignPermissions(?User $user = null, ?Gym $tenant = null): void
    {
        if (! $user || ! $tenant) {
            return;
        }

        // Get all tenant-specific roles
        $roles = DefaultRole::tenantRoles();

        $roleData = array_map(fn($role) => [
            'name' => $role->value,
            'guard_name' => 'web',
            'gym_id' => $tenant->id,
            'created_at' => now(),
            'updated_at' => now(),
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

    public function getDefaultPermissions(?string $role = null)
    {
        $permissionsData = $this->getPermissions();

        $data = [];
        foreach ($permissionsData as $userRole => $rolePermissions) {
            $data[$userRole] = [];

            foreach ($rolePermissions as $category => $permissions) {
                // Extract permission names from each category and merge them
                $permissionNames = collect($permissions)->pluck('name')->toArray();
                $data[$userRole] = array_merge($data[$userRole], $permissionNames);
            }

            // Remove duplicates (if any) and reindex array
            $data[$userRole] = array_values(array_unique($data[$userRole]));
        }

        if (! $role) {
            return $data;
        }

        return $data[$role] ?? $data;
    }

    public function getPermissions(): array
    {
        // Fetch all permissions once and group them by category
        $permissionsByCategory = Permission::orderBy('category')
            ->get(['id', 'name', 'category']) // Select only required columns
            ->groupBy('category')
            ->toArray();

        // Define roles and their allowed categories
        $roleCategories = [
            DefaultRole::GYM_OWNER->value => $this->getGymOwnersCategories(),
            DefaultRole::MANAGER->value => $this->getManagerCategories(),
            DefaultRole::RECEPTIONIST->value => $this->getReceptionistCategories(),
        ];

        $result = [];

        foreach ($roleCategories as $role => $categories) {
            $result[$role] = array_intersect_key($permissionsByCategory, array_flip($categories));
        }

        return $result;
    }

    public function getGymOwnersCategories(): array
    {
        return [
            PermissionCategory::USER->value,
            PermissionCategory::PERMISSION->value,
            PermissionCategory::GYM_MEMBER->value,
            PermissionCategory::MEMBERSHIP_PLAN->value,
            PermissionCategory::TRAINER->value,
            PermissionCategory::RECEPTIONIST->value,
            PermissionCategory::ATTENDANCE->value,
            PermissionCategory::WIDGETS->value,
            PermissionCategory::REPORT->value,
            PermissionCategory::SETTING->value,
            PermissionCategory::COMMUNICATION->value,
            PermissionCategory::GENERAL->value,
            PermissionCategory::MANAGER->value,
            PermissionCategory::PAYMENT->value,
        ];
    }

    public function getManagerCategories(): array
    {
        return [
            PermissionCategory::USER->value,
            PermissionCategory::PERMISSION->value,
            PermissionCategory::GYM_MEMBER->value,
            PermissionCategory::MEMBERSHIP_PLAN->value,
            PermissionCategory::TRAINER->value,
            PermissionCategory::RECEPTIONIST->value,
            PermissionCategory::ATTENDANCE->value,
            PermissionCategory::WIDGETS->value,
            PermissionCategory::REPORT->value,
            PermissionCategory::SETTING->value,
            PermissionCategory::COMMUNICATION->value,
            PermissionCategory::GENERAL->value,
            PermissionCategory::PAYMENT->value,
        ];
    }

    public function getReceptionistCategories(): array
    {
        return [
            PermissionCategory::USER->value,
            PermissionCategory::GYM_MEMBER->value,
            PermissionCategory::MEMBERSHIP_PLAN->value,
            PermissionCategory::TRAINER->value,
            PermissionCategory::ATTENDANCE->value,
            PermissionCategory::WIDGETS->value,
            PermissionCategory::REPORT->value,
            PermissionCategory::COMMUNICATION->value,
            PermissionCategory::GENERAL->value,
            PermissionCategory::PAYMENT->value,
        ];
    }

    /**
     * Assigns a role to a user for a given gym.
     *
     * @throws \Exception If the role does not exist for the given gym.
     */
    public function assignRoleToTenant(Gym $tenant, User $user, DefaultRole $roleName): void
    {
        // Ensure the role exists for the tenant
        $role = Role::where('name', $roleName->value)
            ->where('gym_id', $tenant->id)
            ->first();

        if ($role) {
            setPermissionsTeamId($tenant->id);
            // Assign the role to the user
            $user->assignRole($role);

            // setPermissionsTeamId(Filament::getTenantId());
        } else {
            throw new \Exception("Role {$roleName->value} does not exist for this tenant.");
        }
    }
}
