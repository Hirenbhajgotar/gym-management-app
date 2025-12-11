<?php

namespace App\Services;

use App\Enums\DefaultRole;
use App\Enums\PermissionCategory;
use App\Models\Gym;
use App\Models\User;
use App\Traits\DefaultPermissionResourceTrait;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsService
{
    use DefaultPermissionResourceTrait {
        getDefaultPermissions as traitGetDefaultPermissions;
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

            // Get permissions (checks DB Settings -> fallback to Dynamic Service Logic)
            $permissionsToAssign = $this->getDefaultPermissions();

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
        foreach ($roles as $roleItem) {
            $this->setDefaultRole($roleItem);
            $data[$roleItem] = $this->getTraitDefaultPermissions();
        }

        return $data;
    }

    // Alias to avoid conflict if Trait has same method name (it does)
    protected function getTraitDefaultPermissions(): array
    {
        return $this->traitGetDefaultPermissions();
    }

    /**
     * Generate dynamic default permissions based on Allowed List.
     * We iterate the ALLOWED LIST directly, instead of Filament Resources.
     * This ensures that "Virtual" resources (like Payment, if no Resource class exists)
     * and "Shared Model" resources (Receptionist -> User model) are handled correctly.
     */
    public function getDynamicDefaultPermissions(string $role): array
    {
        $permissions = [];

        // 1. Define standard actions
        $actions = [
            'view_any', 'view', 'create', 'update', 'delete',
            'delete_any', 'force_delete', 'force_delete_any',
            'restore', 'replicate', 'reorder', 'export', 'import',
        ];

        // 2. Get the "Allow List" for this role.
        // This is the source of truth for what they SHOULD have access to.
        $allowedResources = $this->getRoleAllowedResourcesList($role);

        foreach ($allowedResources as $resourceName) {
            foreach ($actions as $action) {
                // EXCLUSION LOGIC

                // Manager Exclusions
                if ($role === DefaultRole::MANAGER->value) {
                    // Managers cannot force delete anything
                    if (in_array($action, ['force_delete', 'force_delete_any'])) {
                        continue;
                    }
                }

                // Receptionist Exclusions
                if ($role === DefaultRole::RECEPTIONIST->value) {
                    // Receptionists should not delete payments
                    if (in_array($action, ['delete', 'delete_any', 'force_delete', 'force_delete_any'])) {
                        continue;
                    }
                }

                // Generate permission string: action_resource
                $permissions[] = "{$action}_{$resourceName}";
            }
        }

        // 3. Add Role Specific / Extra Permissions (Widgets, Pages, etc.)
        $extraPermissions = $this->getRoleSpecificPermissions($role);

        $permissions = array_merge($permissions, $extraPermissions);

        return array_values(array_unique($permissions));
    }

    /**
     * Define the whitelist of resources for each role.
     */
    protected function getRoleAllowedResourcesList(string $role): array
    {
        return match ($role) {
            DefaultRole::MANAGER->value => config('default-roles-and-permissions.' . DefaultRole::MANAGER->value),
            DefaultRole::RECEPTIONIST->value => config('default-roles-and-permissions.' . DefaultRole::RECEPTIONIST->value),
            DefaultRole::TRAINER->value => config('default-roles-and-permissions.' . DefaultRole::TRAINER->value),
            default => [],
        };
    }

    /**
     * Define specific/non-resource permissions.
     */
    protected function getRoleSpecificPermissions(string $role): array
    {
        $common = [
            'access_admin_panel',
            'view_dashboard',
            'page_dashboard',
        ];

        return match ($role) {
            DefaultRole::MANAGER->value => array_merge($common, [
                'widget_gym_revenue_chart',
                'widget_gym_stats_overview',
                'widget_membership_expiry_alerts_chart',
                'widget_recent_activity',
                'widget_todays_birthdays',
                'widget_trainer_performance',
                'page_edit_gym_profile',
                'page_settings',
                'page_reports',
                'page_analytics',
                'page_backup',
                'page_system_health',
                'manage_settings',
                'view_analytics',
                'export_data',
                'import_data',
                'send_notifications',
                'send_bulk_emails',
                'manage_announcements',
                'bulk_actions',
                'advanced_search',
                'view_financial_reports',
                'view_member_reports',
            ]),
            DefaultRole::RECEPTIONIST->value => array_merge($common, [
                'view_any_membership_plan', 'view_membership_plan', // View only for plans
                'view_any_trainer', 'view_trainer', // View trainers
                'view_receptionist', 'update_receptionist', // Own profile
                'send_notifications',
            ]),
            default => [],
        };
    }

    /**
     * Get resource categories for the UI.
     */
    public function getRoleResourceCategories(string $role): array
    {
        return $this->getRoleAllowedResourcesList($role);
    }

    /**
     * Get widget categories for the UI.
     */
    public function getRoleWidgetCategories(string $role): array
    {
        // Assuming all widgets are under 'widgets' category
        return match ($role) {
            DefaultRole::MANAGER->value,
            DefaultRole::RECEPTIONIST->value,
            DefaultRole::TRAINER->value => ['widgets'],
            default => [],
        };
    }

    /**
     * Get report categories for the UI.
     */
    public function getRoleReportCategories(string $role): array
    {
        return match ($role) {
            DefaultRole::MANAGER->value => ['report'], // Enum: REPORT = 'report'
            default => [],
        };
    }

    /**
     * Get other categories for the UI (System, Communication, etc.).
     */
    public function getRoleOtherCategories(string $role): array
    {
        // Based on Seeder and Enum
        return match ($role) {
            DefaultRole::MANAGER->value => ['system', 'communication', 'setting', 'general'],
            DefaultRole::RECEPTIONIST->value => ['communication', 'general'],
            DefaultRole::TRAINER->value => ['general'],
            default => [],
        };
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

    public function getPermissionDisplayName(string $permissionName): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $permissionName));
    }

    public function getPermissionDescription(string $permissionName): string
    {
        return 'can_' . $this->getPermissionDisplayName($permissionName);
    }

    public function getPermissionCategory(string $permissionName): ?string
    {
        return PermissionCategory::tryFrom($permissionName)->value ?? null;
    }
}
