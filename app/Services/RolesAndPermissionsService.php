<?php

namespace App\Services;

use App\Enums\DefaultRole;
use App\Models\Gym;
use App\Models\User;
use App\Traits\DefaultPermissionResourceTrait;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsService
{
    use DefaultPermissionResourceTrait {
        getDefaultPermissions as getTraitDefaultPermissions;
    }

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

        $roleData = array_map(fn ($role) => [
            'name' => $role->value,
            'guard_name' => 'web',
            'gym_id' => $tenant->id,
            'created_at' => now(),
            'updated_at' => now(),
        ], $roles);

        // Use bulk insert to minimize DB queries
        Role::insert($roleData);

        // Now assign permissions to each role using PermissionSetting or Fallback
        foreach ($roles as $role) {
            // Set the role context for the trait logic
            $this->setDefaultRole($role->value);

            // Get permissions (checks DB Settings -> fallback to Trait defaults)
            $permissionsToAssign = $this->getTraitDefaultPermissions();

            if (! empty($permissionsToAssign)) {
                $roleModel = Role::where('name', $role->value)
                    ->where('gym_id', $tenant->id)
                    ->first();

                if ($roleModel) {
                    $roleModel->syncPermissions($permissionsToAssign);
                }
            }
        }
    }

    /**
     * Get default permissions for a role or all roles.
     * Compatible with ImportDefaultPermissionsJob.
     *
     * @return array
     */
    public function getDefaultPermissions(?string $role = null)
    {
        if ($role) {
            $this->setDefaultRole($role);

            return $this->getTraitDefaultPermissions();
        }

        // If no role specified, return map of all roles
        $roles = [
            DefaultRole::MANAGER->value,
            DefaultRole::RECEPTIONIST->value,
            DefaultRole::TRAINER->value,
        ];

        $data = [];
        foreach ($roles as $role) {
            $this->setDefaultRole($role);
            $data[$role] = $this->getTraitDefaultPermissions();
        }

        return $data;
    }

    /**
     * Assigns a role to a user for a given gym.
     *
     * @throws \Exception If the role does not exist for the given gym.
     */
    public function assignRoleToTenant(?Gym $tenant, User $user, DefaultRole $roleName): void
    {
        if (! $tenant) {
            throw new \Exception('Gym not found.');
        }

        // Ensure the role exists for the tenant
        $role = Role::where('name', $roleName->value)
            ->where('gym_id', $tenant->id)
            ->first();

        if ($role) {
            $permissionRegistrar = app(PermissionRegistrar::class);

            $permissionRegistrar->forgetCachedPermissions();

            setPermissionsTeamId($tenant->id);

            // Reload user's roles to ensure role checks use the updated team context
            $user->load('roles');

            // Assign the role to the user
            $user->assignRole($role);

            // setPermissionsTeamId(Filament::getTenantId());
        } else {
            throw new \Exception("Role {$roleName->value} does not exist for this tenant.");
        }
    }
}
