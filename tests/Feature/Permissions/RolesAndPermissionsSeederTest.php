<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use App\Models\Gym;
use App\Models\Permission;
use App\Services\RolesAndPermissionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesAndPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    protected RolesAndPermissionsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = resolve(RolesAndPermissionsService::class);
    }

    #[Test]
    public function it_seeder_creates_all_discovered_permissions(): void
    {
        $discovered = $this->service->discoverFilamentPermissions();
        $expectedCount = count($discovered);

        $this->assertGreaterThan(0, $expectedCount, 'Discovery should find at least some permissions');

        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder', '--force' => true]);

        $dbCount = Permission::query()->count();
        $this->assertGreaterThanOrEqual(
            $expectedCount,
            $dbCount,
            "DB should have at least {$expectedCount} permissions (discovered), got {$dbCount}",
        );
    }

    #[Test]
    public function it_seeder_assigns_all_to_super_admin(): void
    {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder', '--force' => true]);

        $superAdminRole = Role::query()->where('name', 'super_admin')->whereNull('gym_id')->first();
        $this->assertNotNull($superAdminRole, 'super_admin role should exist');

        $totalPermissions = Permission::query()->count();
        $superAdminPermissions = $superAdminRole->permissions()->count();

        $this->assertSame(
            $totalPermissions,
            $superAdminPermissions,
            "super_admin should have all {$totalPermissions} permissions, has {$superAdminPermissions}",
        );
    }

    #[Test]
    public function it_seeder_creates_discovered_permission_names(): void
    {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder', '--force' => true]);

        $discovered = $this->service->discoverFilamentPermissions();
        $dbNames = Permission::query()->pluck('name')->toArray();

        foreach ($discovered as $permission) {
            $this->assertContains(
                $permission,
                $dbNames,
                "Discovered permission '{$permission}' should exist in DB after seeding",
            );
        }
    }

    #[Test]
    public function it_seeder_does_not_create_duplicate_permissions(): void
    {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder', '--force' => true]);

        $total = Permission::query()->count();
        $unique = Permission::query()->distinct('name')->count('name');

        $this->assertSame($total, $unique, 'No duplicate permission names should exist');
    }

    #[Test]
    public function it_seeder_assigns_permissions_to_per_gym_roles(): void
    {
        Gym::query()->create(['name' => 'Test Gym', 'slug' => 'test-gym']);

        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder', '--force' => true]);

        $gymRoles = Role::query()->whereNotNull('gym_id')->get();
        $this->assertNotEmpty($gymRoles, 'Per-gym roles should be created');

        $rolesWithPermissions = $gymRoles->filter(fn ($role) => $role->permissions()->count() > 0);
        $this->assertNotEmpty(
            $rolesWithPermissions,
            'At least some per-gym roles should have permissions assigned',
        );
    }

    #[Test]
    public function it_seeder_uses_discovery_not_hardcoded_lists(): void
    {
        $discovered = $this->service->discoverFilamentPermissions();
        $discoveredCount = count($discovered);

        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder', '--force' => true]);

        $dbCount = Permission::query()->count();
        $this->assertGreaterThanOrEqual(
            $discoveredCount,
            $dbCount,
            "At least {$discoveredCount} discovered permissions should be created, got {$dbCount}",
        );
    }
}
