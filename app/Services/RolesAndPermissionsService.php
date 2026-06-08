<?php

namespace App\Services;

use App\Enums\DefaultRole;
use App\Enums\PermissionCategory;
use App\Models\Gym;
use App\Models\Permission;
use App\Models\User;
use App\Traits\DefaultPermissionResourceTrait;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
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

        $guardName = config('filament-permissions.guard_name', 'web');

        DB::transaction(function () use ($tenant, $guardName) {
            // Get all tenant-specific roles
            $roles = DefaultRole::tenantRoles();

            // Idempotent check: If roles already exist, skip.
            $existingCount = Role::where('gym_id', $tenant->id)
                ->whereIn('name', array_column($roles, 'value'))
                ->count();

            if ($existingCount > 0) {
                return;
            }

            $roleData = array_map(fn ($role) => [
                'name' => $role->value,
                'guard_name' => $guardName,
                'gym_id' => $tenant->id,
                'created_at' => now(),
                'updated_at' => now(),
            ], $roles);

            // Use bulk insert to minimize DB queries
            Role::insert($roleData);

            // Fetch the created role IDs mapped by name
            $createdRoles = Role::whereIn('name', array_column($roleData, 'name'))
                ->where('gym_id', $tenant->id)
                ->pluck('id', 'name');

            $permissionInsertData = [];

            // Now assign permissions to each role using PermissionSetting or Fallback
            foreach ($roles as $role) {
                if (! isset($createdRoles[$role->value])) {
                    continue;
                }

                // Set the role context for the trait logic
                $this->setDefaultRole($role->value);

                // Get permissions (checks DB Settings -> fallback to Dynamic Service Logic)
                $permissionsToAssign = $this->getDefaultPermissions($role->value);

                if (! empty($permissionsToAssign)) {
                    // Fetch IDs for these permission names efficiently
                    $permissionIds = Permission::whereIn('name', $permissionsToAssign)->pluck('id');
                    $roleId = $createdRoles[$role->value];

                    foreach ($permissionIds as $permissionId) {
                        $permissionInsertData[] = [
                            'permission_id' => $permissionId,
                            'role_id' => $roleId,
                        ];
                    }
                }
            }

            if (! empty($permissionInsertData)) {
                // Bulk insert all permissions
                // Optimize chunk size if necessary, but for valid roles this is safe enough
                foreach (array_chunk($permissionInsertData, 1000) as $chunk) {
                    DB::table('role_has_permissions')->insert($chunk);
                }

                // Forget cache only once at the end
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            }
        });
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
            DefaultRole::MANAGER->value => (array) config('default-roles-and-permissions.' . DefaultRole::MANAGER->value),
            DefaultRole::RECEPTIONIST->value => (array) config('default-roles-and-permissions.' . DefaultRole::RECEPTIONIST->value),
            DefaultRole::TRAINER->value => (array) config('default-roles-and-permissions.' . DefaultRole::TRAINER->value),
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
            // Trainers need base panel access plus their resource permissions
            // (attendance, gym_member from config). They don't get management
            // widgets or settings — those are manager-only.
            DefaultRole::TRAINER->value => $common,
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
     * When Spatie teams are enabled, assignRole() reads
     * getPermissionsTeamId() to write the team foreign key (gym_id)
     * into the model_has_roles pivot. The roles() relationship also
     * filters by this value. We therefore set the team ID for the
     * duration of the assignment and restore it afterward.
     *
     * This is safe under sequential execution (HTTP requests, queue
     * workers) because PHP is single-threaded and try/finally
     * guarantees the restore. Under Laravel Octane, each worker
     * process has its own static copy, so there is no cross-request
     * contamination.
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

        if (! $role) {
            throw new \Exception("Role {$roleName->value} does not exist for this tenant.");
        }

        // Set the team ID so Spatie writes the correct gym_id to the
        // pivot and the roles() relationship can see the role.
        // try/finally guarantees restore even on exceptions.
        $previousTeamId = getPermissionsTeamId();
        try {
            setPermissionsTeamId($tenant->id);
            $user->assignRole($role);
        } finally {
            setPermissionsTeamId($previousTeamId);
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
        $category = PermissionCategory::tryFrom($permissionName);

        return $category?->value;
    }

    public function discoverFilamentPermissions(): array
    {
        $permissions = [];
        $panelIds = config('filament-permissions.panels', []);
        $actions = config('filament-permissions.resource_actions', []);

        foreach (Filament::getPanels() as $id => $panel) {
            if (! in_array($id, $panelIds, true)) {
                continue;
            }

            foreach ($panel->getResources() as $resourceClass) {
                $reflection = $this->safeReflection($resourceClass);

                if ($reflection === null || $reflection->isAbstract()) {
                    continue;
                }

                if (! $reflection->implementsInterface(\App\Contracts\HasFilamentPermissions::class)) {
                    continue;
                }

                // Derive slug from the MODEL class name, not the Resource class name.
                // This matches how BasePolicy generates permission names via $tableName.
                // e.g. WhatsAppTransactionResource → model WhatsAppCreditTransaction → whatsapp_credit_transaction
                $modelClass = $this->resolveResourceModel($reflection);
                $prefix = $this->deriveSlugFromModelOrResource($modelClass, $reflection);

                $permissions = array_merge($permissions, $this->generateResourcePermissions($prefix, $actions));
            }

            foreach ($panel->getPages() as $pageClass) {
                $reflection = $this->safeReflection($pageClass);

                if ($reflection === null || $reflection->isAbstract()) {
                    continue;
                }

                if (! $reflection->implementsInterface(\App\Contracts\HasFilamentPermissions::class)) {
                    continue;
                }

                $prefix = $this->deriveSlugFromClassName($reflection->getShortName(), 'Page');
                $permissions[] = $this->generatePagePermission($prefix);
            }

            foreach ($panel->getWidgets() as $widgetClass) {
                $reflection = $this->safeReflection($widgetClass);

                if ($reflection === null || $reflection->isAbstract()) {
                    continue;
                }

                if (! $reflection->implementsInterface(\App\Contracts\HasFilamentPermissions::class)) {
                    continue;
                }

                $prefix = $this->deriveSlugFromClassName($reflection->getShortName(), 'Widget');
                $permissions[] = $this->generateWidgetPermission($prefix);
            }
        }

        return array_values(array_unique($permissions));
    }

    private function safeReflection(string $className): ?\ReflectionClass
    {
        try {
            return new \ReflectionClass($className);
        } catch (\ReflectionException) {
            return null;
        }
    }

    public function generateResourcePermissions(string $prefix, ?array $actions = null): array
    {
        $actions ??= config('filament-permissions.resource_actions', []);
        $guard = config('filament-permissions.guard_name', 'web');

        return array_map(
            fn (string $action) => "{$action}_{$prefix}",
            $actions,
        );
    }

    public function generatePagePermission(string $slug): string
    {
        $prefix = config('filament-permissions.page_prefix', 'page_');

        return "{$prefix}{$slug}";
    }

    public function generateWidgetPermission(string $slug): string
    {
        $prefix = config('filament-permissions.widget_prefix', 'widget_');

        return "{$prefix}{$slug}";
    }

    public function deriveSlugFromClassName(string $className, string $suffix): string
    {
        $name = preg_replace('/' . preg_quote($suffix, '/') . '$/', '', $className);

        // split on word boundaries (handles acronyms like QR, CRM)
        $name = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $name);
        $name = preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $name);

        return strtolower(trim($name, '_'));
    }

    /**
     * Derive permission slug from the Model class name when available,
     * falling back to the Resource class name.
     *
     * This ensures generated permissions match BasePolicy's $tableName convention
     * (e.g. WhatsAppCreditTransaction → whatsapp_credit_transaction, not whatsapp_transaction).
     */
    private function deriveSlugFromModelOrResource(?string $modelClass, \ReflectionClass $resourceReflection): string
    {
        if ($modelClass !== null) {
            // Prefer the policy's $tableName — it's the source of truth for permission naming.
            $tableName = $this->resolvePolicyTableName($modelClass);

            if ($tableName !== null) {
                return $tableName;
            }

            // Fallback to deriving from model class name
            $modelReflection = $this->safeReflection($modelClass);

            if ($modelReflection !== null) {
                return $this->deriveSlugFromClassName($modelReflection->getShortName(), '');
            }
        }

        return $this->deriveSlugFromClassName($resourceReflection->getShortName(), 'Resource');
    }

    /**
     * Resolve the model class name from a Resource's static $model property.
     * Uses reflection because direct static property access via string variable
     * returns null in PHP.
     */
    private function resolveResourceModel(\ReflectionClass $resourceReflection): ?string
    {
        if (! $resourceReflection->hasProperty('model')) {
            return null;
        }

        $prop = $resourceReflection->getProperty('model');
        $prop->setAccessible(true);

        return $prop->getValue();
    }

    /**
     * Get the $tableName from a model's policy class.
     * Policies that extend BasePolicy set $tableName which is used for permission naming.
     *
     * Tries multiple naming patterns to handle policy classes that don't follow
     * the Laravel convention (e.g. WhatsAppTransactionPolicy for WhatsAppCreditTransaction model).
     */
    private function resolvePolicyTableName(string $modelClass): ?string
    {
        $modelName = class_basename($modelClass);

        // Try standard convention: {Model}Policy
        $tableName = $this->getTableNameFromPolicy('App\\Policies\\' . $modelName . 'Policy');

        if ($tableName !== null) {
            return $tableName;
        }

        // Try common variations where policy name differs from model name.
        // e.g. WhatsAppCreditTransaction → WhatsAppTransactionPolicy
        $prefixes = ['WhatsApp'];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($modelName, $prefix) && strlen($modelName) > strlen($prefix)) {
                $shortName = substr($modelName, strlen($prefix));
                $candidatePolicy = 'App\\Policies\\' . $prefix . 'TransactionPolicy';

                // Only try if the short name contains "Transaction" or similar patterns
                if (str_contains($shortName, 'Credit')) {
                    $tableName = $this->getTableNameFromPolicy($candidatePolicy);

                    if ($tableName !== null) {
                        return $tableName;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get $tableName from a specific policy class if it extends BasePolicy.
     */
    private function getTableNameFromPolicy(string $policyClass): ?string
    {
        if (! class_exists($policyClass)) {
            return null;
        }

        $policyReflection = $this->safeReflection($policyClass);

        if ($policyReflection === null || $policyReflection->isAbstract()) {
            return null;
        }

        if (! $policyReflection->isSubclassOf('App\\Policies\\BasePolicy')) {
            return null;
        }

        if (! $policyReflection->hasProperty('tableName')) {
            return null;
        }

        $prop = $policyReflection->getProperty('tableName');
        $prop->setAccessible(true);

        return $prop->getValue();
    }
}
