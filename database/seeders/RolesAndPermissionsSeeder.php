<?php

namespace Database\Seeders;

use App\Enums\DefaultRole;
use App\Models\Gym;
use App\Models\Permission as ModelsPermission;
use App\Services\RolesAndPermissionsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Optimized RolesAndPermissionsSeeder
 *
 * This seeder creates:
 * 1. All permissions (global, no gym_id)
 * 2. Global super_admin role with all permissions
 * 3. Per-gym roles (gym_owner, manager, receptionist, trainer, member)
 *    using RolesAndPermissionsService for efficient batch inserts
 *
 * Memory optimization:
 * - Uses batch inserts instead of sync operations
 * - Chunks permission assignments to avoid memory exhaustion
 * - Only creates roles for gyms that exist
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function __construct(
        protected RolesAndPermissionsService $rolesService
    ) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info('🔄 Creating permissions...');

        // Create all permissions
        $allPermissions = $this->createAllPermissions();

        $this->info('✅ Created ' . count($allPermissions) . ' permissions');

        // Create global super_admin role
        $this->info('🔄 Creating super_admin role...');
        $this->createSuperAdminRole($allPermissions);

        // Create per-gym roles efficiently
        $this->info('🔄 Creating per-gym roles...');
        $this->createPerGymRoles();

        $this->info('✅ Roles and permissions seeded successfully!');

        // Display summary
        $this->displaySummary();
    }

    private function createAllPermissions(): array
    {
        $permissions = [];

        $guardName = config('filament-permissions.guard_name', 'web');

        $discoveredPermissions = $this->rolesService->discoverFilamentPermissions();

        foreach ($discoveredPermissions as $permissionName) {
            $permission = ModelsPermission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => $guardName,
            ]);
            $permissions[] = $permission;
        }

        $this->info('   📋 Discovered ' . count($discoveredPermissions) . ' permissions from Filament classes');

        return $permissions;
    }

    /**
     * Create global super_admin role with all permissions
     */
    private function createSuperAdminRole(array $allPermissions): void
    {
        $superAdminRole = Role::firstOrCreate([
            'name' => DefaultRole::SUPER_ADMIN->value,
            'guard_name' => 'web',
        ]);

        // Use sync for super_admin since it's only one role
        $superAdminRole->syncPermissions($allPermissions);

        $this->info('   ✅ super_admin: ' . count($allPermissions) . ' permissions');
    }

    /**
     * Create per-gym roles using efficient batch inserts
     * Handles gyms with and without owners
     */
    private function createPerGymRoles(): void
    {
        $gyms = Gym::all();

        if ($gyms->isEmpty()) {
            $this->info('   ⚠️ No gyms found. Skipping per-gym roles.');

            return;
        }

        // Get all tenant-specific roles
        $tenantRoles = DefaultRole::tenantRoles();

        $rolesCreated = 0;
        $permissionsAssigned = 0;

        foreach ($gyms as $gym) {
            // Check if roles already exist for this gym
            $existingRoles = Role::where('gym_id', $gym->id)->count();

            if ($existingRoles > 0) {
                continue; // Skip gyms that already have roles
            }

            // Create roles for this gym
            $roleData = array_map(fn ($role) => [
                'name' => $role->value,
                'guard_name' => 'web',
                'gym_id' => $gym->id,
                'created_at' => now(),
                'updated_at' => now(),
            ], $tenantRoles);

            // Bulk insert roles
            Role::insert($roleData);
            $rolesCreated += count($roleData);

            // Fetch the created role IDs
            $createdRoles = Role::whereIn('name', array_column($roleData, 'name'))
                ->where('gym_id', $gym->id)
                ->pluck('id', 'name');

            // Assign default permissions to roles
            $permissionInsertData = [];

            foreach ($tenantRoles as $role) {
                if (! isset($createdRoles[$role->value])) {
                    continue;
                }

                // Get default permissions for this role from service
                $defaultPermissions = $this->rolesService->getDynamicDefaultPermissions($role->value);

                if (! empty($defaultPermissions)) {
                    $permissionIds = Permission::whereIn('name', $defaultPermissions)->pluck('id');
                    $roleId = $createdRoles[$role->value];

                    foreach ($permissionIds as $permissionId) {
                        $permissionInsertData[] = [
                            'permission_id' => $permissionId,
                            'role_id' => $roleId,
                        ];
                    }
                }
            }

            // Bulk insert permissions for this gym's roles
            if (! empty($permissionInsertData)) {
                foreach (array_chunk($permissionInsertData, 500) as $chunk) {
                    DB::table('role_has_permissions')->insert($chunk);
                }
                $permissionsAssigned += count($permissionInsertData);
            }
        }

        $this->info("   📊 Created {$rolesCreated} roles with {$permissionsAssigned} permission assignments");
    }

    /**
     * Display summary of created roles and permissions
     */
    private function displaySummary(): void
    {
        $totalPermissions = Permission::count();
        $totalRoles = Role::count();
        $globalRoles = Role::whereNull('gym_id')->count();
        $gymRoles = Role::whereNotNull('gym_id')->count();
        $pivotCount = DB::table('role_has_permissions')->count();

        $this->info('');
        $this->info('📊 Summary:');
        $this->info("   📋 Total permissions: {$totalPermissions}");
        $this->info("   👥 Global roles: {$globalRoles}");
        $this->info("   🏢 Per-gym roles: {$gymRoles}");
        $this->info("   🔗 Role-permission links: {$pivotCount}");
    }

    /**
     * Helper to output info messages
     */
    private function info(string $message): void
    {
        if (isset($this->command)) {
            $this->command->info($message);
        }
    }
}
