<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DefaultRole;
use App\Models\Permission;
use App\Services\RolesAndPermissionsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sync permissions with roles when new resources/pages are added.
 *
 * This command:
 * 1. Creates any missing permissions from resources/pages
 * 2. Syncs gym_owner roles with ALL permissions
 * 3. Syncs super_admin role with ALL permissions
 * 4. Syncs other roles with their configured subset of permissions
 */
class SyncPermissionsCommand extends Command
{
    protected $signature = 'permissions:sync
                            {--gym= : Sync permissions for a specific gym ID only}
                            {--force : Re-sync all permissions (not just new ones)}
                            {--dry-run : Show what would be changed without making changes}';

    protected $description = 'Sync permissions with roles when new resources or pages are added';

    public function __construct(
        protected RolesAndPermissionsService $rolesService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $gymId = $this->option('gym') ? (int) $this->option('gym') : null;

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        $this->info('🔄 Starting permission sync...');
        $this->newLine();

        // Step 1: Create any missing permissions
        $newPermissions = $this->createMissingPermissions($dryRun);

        // Step 2: Sync super_admin role
        $superAdminSynced = $this->syncSuperAdminPermissions($dryRun, $force);

        // Step 3: Sync gym_owner roles
        $gymOwnerStats = $this->syncGymOwnerPermissions($gymId, $dryRun, $force);

        // Step 4: Sync other tenant roles (manager, receptionist, trainer)
        $otherRolesStats = $this->syncOtherTenantRoles($gymId, $dryRun, $force);

        // Clear permission cache — always flush globally since permission names are unique
        // and the cache is not team-scoped. The setPermissionsTeamId() approach was removed
        // because it mutates global state, which is unsafe under concurrent execution
        // (Octane, queue workers) and leaks team context across requests.
        if (! $dryRun) {
            $registrar = resolve(PermissionRegistrar::class);
            $registrar->forgetCachedPermissions();
            $this->info('   🗑️ Cleared global permission cache');
        }

        $this->newLine();
        $this->displaySummary($newPermissions, $superAdminSynced, $gymOwnerStats, $otherRolesStats);

        return Command::SUCCESS;
    }

    protected function createMissingPermissions(bool $dryRun): int
    {
        $this->info('📋 Checking for missing permissions...');

        $guardName = config('filament-permissions.guard_name', 'web');

        $discoveredPermissions = $this->rolesService->discoverFilamentPermissions();

        $this->info('   🔍 Discovered ' . count($discoveredPermissions) . ' permissions from Filament classes');

        $existingPermissions = Permission::pluck('name')->toArray();
        $missingPermissions = array_diff($discoveredPermissions, $existingPermissions);

        if (blank($missingPermissions)) {
            $this->info('   ✅ All permissions already exist');

            return 0;
        }

        $this->info('   📝 Found ' . count($missingPermissions) . ' missing permissions');

        if ($dryRun) {
            foreach (array_slice($missingPermissions, 0, 10) as $permission) {
                $this->line("      - {$permission}");
            }
            if (count($missingPermissions) > 10) {
                $this->line('      ... and ' . (count($missingPermissions) - 10) . ' more');
            }

            return count($missingPermissions);
        }

        $permissionData = array_map(fn ($name) => [
            'name' => $name,
            'guard_name' => $guardName,
            'created_at' => now(),
            'updated_at' => now(),
        ], $missingPermissions);

        Permission::insert($permissionData);
        $this->info('   ✅ Created ' . count($missingPermissions) . ' new permissions');

        return count($missingPermissions);
    }

    /**
     * Sync super_admin role with all permissions.
     */
    protected function syncSuperAdminPermissions(bool $dryRun, bool $force): int
    {
        $this->info('👑 Syncing super_admin role...');

        $superAdminRole = Role::where('name', DefaultRole::SUPER_ADMIN->value)
            ->whereNull('gym_id')
            ->first();

        if (! $superAdminRole) {
            $this->warn('   ⚠️ super_admin role not found');

            return 0;
        }

        $allPermissions = Permission::all();
        $currentCount = $superAdminRole->permissions()->count();

        if ($currentCount === $allPermissions->count() && ! $force) {
            $this->info("   ✅ Already has all {$currentCount} permissions");

            return 0;
        }

        $diff = $allPermissions->count() - $currentCount;

        if ($dryRun) {
            $this->info("   📝 Would sync {$diff} new permissions (total: {$allPermissions->count()})");

            return $diff;
        }

        $superAdminRole->syncPermissions($allPermissions);
        $this->info("   ✅ Synced {$allPermissions->count()} permissions (+{$diff} new)");

        return $diff;
    }

    /**
     * Sync gym_owner roles with all permissions.
     */
    protected function syncGymOwnerPermissions(?int $gymId, bool $dryRun, bool $force): array
    {
        $this->info('🏢 Syncing gym_owner roles...');

        $query = Role::where('name', DefaultRole::GYM_OWNER->value)
            ->whereNotNull('gym_id');

        if ($gymId) {
            $query->where('gym_id', $gymId);
        }

        $gymOwnerRoles = $query->get();

        if ($gymOwnerRoles->isEmpty()) {
            $this->warn('   ⚠️ No gym_owner roles found');

            return ['synced' => 0, 'permissions_added' => 0];
        }

        $allPermissions = Permission::all();
        $allPermissionIds = $allPermissions->pluck('id')->toArray();

        $rolesSynced = 0;
        $permissionsAdded = 0;

        foreach ($gymOwnerRoles as $role) {
            $currentPermissionIds = $role->permissions()->pluck('id')->toArray();
            $missingPermissionIds = array_diff($allPermissionIds, $currentPermissionIds);

            if (blank($missingPermissionIds) && ! $force) {
                continue;
            }

            $rolesSynced++;
            $permissionsAdded += count($missingPermissionIds);

            if ($dryRun) {
                $this->line("      - Gym #{$role->gym_id}: +{}" . count($missingPermissionIds) . ' permissions');

                continue;
            }

            // Use bulk insert for efficiency
            $insertData = array_map(fn ($permissionId) => [
                'permission_id' => $permissionId,
                'role_id' => $role->id,
            ], $missingPermissionIds);

            if (filled($insertData)) {
                DB::table('role_has_permissions')->insertOrIgnore($insertData);
            }
        }

        if ($rolesSynced === 0) {
            $this->info('   ✅ All gym_owner roles already have all permissions');
        } else {
            $action = $dryRun ? 'Would sync' : 'Synced';
            $this->info("   ✅ {$action} {$rolesSynced} gym_owner roles (+{$permissionsAdded} permissions)");
        }

        return ['synced' => $rolesSynced, 'permissions_added' => $permissionsAdded];
    }

    /**
     * Sync other tenant roles (manager, receptionist, trainer) with their configured permissions.
     */
    protected function syncOtherTenantRoles(?int $gymId, bool $dryRun, bool $force): array
    {
        $this->info('👥 Syncing other tenant roles (manager, receptionist, trainer)...');

        $rolesToSync = [
            DefaultRole::MANAGER->value,
            DefaultRole::RECEPTIONIST->value,
            DefaultRole::TRAINER->value,
        ];

        $stats = ['synced' => 0, 'permissions_added' => 0];

        $discoveredAssignments = $this->rolesService->getDiscoveredDefaultRoleAssignments();

        foreach ($rolesToSync as $roleName) {
            $query = Role::where('name', $roleName)->whereNotNull('gym_id');

            if ($gymId) {
                $query->where('gym_id', $gymId);
            }

            foreach ($query->cursor() as $role) {
                $defaultPermissions = $discoveredAssignments[$roleName]
                    ?? $this->rolesService->getDynamicDefaultPermissions($roleName);
                $permissionIds = Permission::whereIn('name', $defaultPermissions)->pluck('id')->toArray();

                $currentPermissionIds = $role->permissions()->pluck('id')->toArray();
                $missingPermissionIds = array_diff($permissionIds, $currentPermissionIds);

                if (blank($missingPermissionIds) && ! $force) {
                    continue;
                }

                $stats['synced']++;
                $stats['permissions_added'] += count($missingPermissionIds);

                if ($dryRun) {
                    continue;
                }

                $insertData = array_map(fn ($permissionId) => [
                    'permission_id' => $permissionId,
                    'role_id' => $role->id,
                ], $missingPermissionIds);

                if (filled($insertData)) {
                    DB::table('role_has_permissions')->insertOrIgnore($insertData);
                }
            }
        }

        if ($stats['synced'] === 0) {
            $this->info('   ✅ All tenant roles already have their configured permissions');
        } else {
            $action = $dryRun ? 'Would sync' : 'Synced';
            $this->info("   ✅ {$action} {$stats['synced']} roles (+{$stats['permissions_added']} permissions)");
        }

        return $stats;
    }

    /**
     * Display final summary.
     */
    protected function displaySummary(int $newPermissions, int $superAdminSynced, array $gymOwnerStats, array $otherRolesStats): void
    {
        $this->info('📊 Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['New permissions created', $newPermissions],
                ['super_admin permissions added', $superAdminSynced],
                ['gym_owner roles synced', $gymOwnerStats['synced']],
                ['gym_owner permissions added', $gymOwnerStats['permissions_added']],
                ['Other roles synced', $otherRolesStats['synced']],
                ['Other roles permissions added', $otherRolesStats['permissions_added']],
            ]
        );

        $totalPermissions = Permission::count();
        $totalRoles = Role::count();
        $totalLinks = DB::table('role_has_permissions')->count();

        $this->newLine();
        $this->info("📈 Totals: {$totalPermissions} permissions, {$totalRoles} roles, {$totalLinks} role-permission links");
    }
}
