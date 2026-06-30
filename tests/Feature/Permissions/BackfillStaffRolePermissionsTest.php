<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use App\Models\Gym;
use App\Models\Permission;
use App\Models\PermissionSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BackfillStaffRolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The 24 permission slugs the migration grants to manager + receptionist
     * when no `PermissionSetting` override exists. Mirrors the array in
     * `ManagerPermissionsPage::getQuickSetupPermissions()`.
     */
    private const QUICK_SETUP_PERMISSIONS = [
        'view_gym_member',
        'view_any_gym_member',
        'create_gym_member',
        'edit_gym_member',
        'view_membership_plan',
        'view_any_membership_plan',
        'create_membership_plan',
        'edit_membership_plan',
        'view_payment',
        'view_any_payment',
        'create_payment',
        'edit_payment',
        'widget_stats_overview',
        'widget_recent_members',
        'widget_membership_expiry_alerts_chart',
        'widget_todays_birthdays',
        'access_notifications',
        'manage_member_notes',
        'send_notifications',
        'access_member_communication',
        'view_lead',
        'view_any_lead',
        'create_lead',
        'edit_lead',
    ];

    private function seedGymWithManagerAndReceptionist(): Gym
    {
        $gym = Gym::query()->create(['name' => 'Test Gym', 'slug' => 'test-gym']);

        // Pre-create the gym_owner + manager + receptionist roles for this gym.
        // (The migration is a backfill, not a role bootstrap.)
        Role::query()->firstOrCreate(['name' => 'gym_owner', 'guard_name' => 'web', 'gym_id' => $gym->id]);
        Role::query()->firstOrCreate(['name' => 'manager', 'guard_name' => 'web', 'gym_id' => $gym->id]);
        Role::query()->firstOrCreate(['name' => 'receptionist', 'guard_name' => 'web', 'gym_id' => $gym->id]);

        $owner = User::factory()->create();
        setPermissionsTeamId($gym->id);
        $owner->assignRole('gym_owner');

        $manager = User::factory()->create();
        setPermissionsTeamId($gym->id);
        $manager->assignRole('manager');

        $receptionist = User::factory()->create();
        setPermissionsTeamId($gym->id);
        $receptionist->assignRole('receptionist');

        return $gym;
    }

    #[Test]
    public function it_grants_quick_setup_permissions_to_existing_manager_and_receptionist(): void
    {
        $this->seedGymWithManagerAndReceptionist();

        // Sanity: before migration, the manager role has no permissions.
        $managerRole = Role::query()->where('name', 'manager')->first();
        $this->assertEquals(0, $managerRole->permissions()->count());

        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_06_02_000001_backfill_staff_role_default_permissions.php',
            '--force' => true,
        ]);

        // Reload to pick up DB changes.
        $managerRole = Role::query()->where('name', 'manager')->where('guard_name', 'web')->first();
        $receptionistRole = Role::query()->where('name', 'receptionist')->where('guard_name', 'web')->first();

        $managerPermissionNames = $managerRole->permissions->pluck('name')->all();
        $receptionistPermissionNames = $receptionistRole->permissions->pluck('name')->all();

        $this->assertEqualsCanonicalizing(
            self::QUICK_SETUP_PERMISSIONS,
            $managerPermissionNames,
            'Manager role should have all 24 quick-setup permissions',
        );
        $this->assertEqualsCanonicalizing(
            self::QUICK_SETUP_PERMISSIONS,
            $receptionistPermissionNames,
            'Receptionist role should have all 24 quick-setup permissions',
        );

        // All 24 permission rows should exist in the permissions table.
        $this->assertSame(
            count(self::QUICK_SETUP_PERMISSIONS),
            Permission::query()->whereIn('name', self::QUICK_SETUP_PERMISSIONS)->count(),
            'All 24 quick-setup permissions should exist in the permissions table',
        );
    }

    #[Test]
    public function it_is_idempotent_when_run_twice(): void
    {
        $this->seedGymWithManagerAndReceptionist();

        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_06_02_000001_backfill_staff_role_default_permissions.php',
            '--force' => true,
        ]);
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_06_02_000001_backfill_staff_role_default_permissions.php',
            '--force' => true,
        ]);

        $managerRole = Role::query()->where('name', 'manager')->where('guard_name', 'web')->first();
        $receptionistRole = Role::query()->where('name', 'receptionist')->where('guard_name', 'web')->first();

        // Each role has exactly 24 permission rows, not 48.
        $this->assertCount(
            count(self::QUICK_SETUP_PERMISSIONS),
            $managerRole->permissions,
            'Manager role should still have exactly 24 permissions after re-run',
        );
        $this->assertCount(
            count(self::QUICK_SETUP_PERMISSIONS),
            $receptionistRole->permissions,
            'Receptionist role should still have exactly 24 permissions after re-run',
        );
    }

    #[Test]
    public function it_uses_permission_setting_override_when_present(): void
    {
        $this->seedGymWithManagerAndReceptionist();

        // Admin pre-configured a custom permission set for `manager`.
        // Only 2 perms (subset of the hardcoded list).
        $override = ['view_any_gym_member', 'view_payment'];
        PermissionSetting::query()->create([
            'role' => 'manager',
            'default_resources' => ['gym_member', 'payment'],
            'default_permissions' => $override,
        ]);

        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_06_02_000001_backfill_staff_role_default_permissions.php',
            '--force' => true,
        ]);

        $managerRole = Role::query()->where('name', 'manager')->where('guard_name', 'web')->first();
        $receptionistRole = Role::query()->where('name', 'receptionist')->where('guard_name', 'web')->first();

        // Manager should use the override (2 perms), not the hardcoded list.
        $this->assertEqualsCanonicalizing(
            $override,
            $managerRole->permissions->pluck('name')->all(),
            'Manager role should use PermissionSetting override, not hardcoded list',
        );

        // Receptionist still has no override, so it gets the hardcoded list.
        $this->assertEqualsCanonicalizing(
            self::QUICK_SETUP_PERMISSIONS,
            $receptionistRole->permissions->pluck('name')->all(),
            'Receptionist role should still get the hardcoded list (no override)',
        );
    }
}
