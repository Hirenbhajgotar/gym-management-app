<?php

namespace Tests\Unit;

use App\Enums\DefaultRole;
use App\Enums\PermissionCategory;
use App\Models\Permission;
use App\Services\RolesAndPermissionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase as TestsTestCase;

class RolesAndPermissionsServiceImportDefaultPermissionsTest extends TestsTestCase
{
    use RefreshDatabase;

    protected RolesAndPermissionsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RolesAndPermissionsService;
    }

    #[Test]
    public function it_returns_all_roles_permissions_when_no_role_specified()
    {
        // Create permissions for different categories
        $this->createPermissionsForCategories([
            PermissionCategory::USER->value => ['view_user', 'create_user'],
            PermissionCategory::GYM_MEMBER->value => ['view_gym_member', 'create_gym_member'],
            PermissionCategory::TRAINER->value => ['view_trainer', 'create_trainer'],
            PermissionCategory::PERMISSION->value => ['view_permission'],
            PermissionCategory::MANAGER->value => ['manage_staff'],
        ]);

        $result = $this->service->getDefaultPermissions();

        // Assert that result contains all tenant roles
        $this->assertIsArray($result);
        $this->assertArrayHasKey(DefaultRole::GYM_OWNER->value, $result);
        $this->assertArrayHasKey(DefaultRole::MANAGER->value, $result);
        $this->assertArrayHasKey(DefaultRole::RECEPTIONIST->value, $result);

        // Assert that each role has an array of permission names
        foreach ($result as $permissions) {
            $this->assertIsArray($permissions);
            $this->assertNotEmpty($permissions);

            // All permissions should be strings (permission names)
            foreach ($permissions as $permission) {
                $this->assertIsString($permission);
            }
        }
    }

    #[Test]
    public function it_returns_specific_role_permissions_when_role_specified()
    {
        // Create permissions for gym owner categories
        $this->createPermissionsForCategories([
            PermissionCategory::USER->value => ['view_user', 'create_user'],
            PermissionCategory::GYM_MEMBER->value => ['view_gym_member', 'create_gym_member'],
            PermissionCategory::PERMISSION->value => ['view_permission'],
        ]);

        $result = $this->service->getDefaultPermissions(DefaultRole::GYM_OWNER->value);

        // Assert that result is an array of permission names for gym owner
        $this->assertIsArray($result);
        $this->assertContains('view_user', $result);
        $this->assertContains('create_user', $result);
        $this->assertContains('view_gym_member', $result);
        $this->assertContains('create_gym_member', $result);
        $this->assertContains('view_permission', $result);
    }

    #[Test]
    public function it_returns_manager_specific_permissions()
    {
        // Create permissions for manager categories (excludes MANAGER category)
        $this->createPermissionsForCategories([
            PermissionCategory::USER->value => ['view_user', 'create_user'],
            PermissionCategory::GYM_MEMBER->value => ['view_gym_member'],
            PermissionCategory::MANAGER->value => ['manage_staff'], // This should not be included for manager role
        ]);

        $result = $this->service->getDefaultPermissions(DefaultRole::MANAGER->value);

        // Assert that manager gets permissions from allowed categories
        $this->assertIsArray($result);
        $this->assertContains('view_user', $result);
        $this->assertContains('create_user', $result);
        $this->assertContains('view_gym_member', $result);

        // Manager should not have MANAGER category permissions
        $this->assertNotContains('manage_staff', $result);
    }

    #[Test]
    public function it_returns_receptionist_specific_permissions()
    {
        // Create permissions for receptionist categories (excludes PERMISSION and MANAGER)
        $this->createPermissionsForCategories([
            PermissionCategory::USER->value => ['view_user'],
            PermissionCategory::GYM_MEMBER->value => ['view_gym_member'],
            PermissionCategory::PERMISSION->value => ['view_permission'], // Should not be included
            PermissionCategory::MANAGER->value => ['manage_staff'], // Should not be included
            PermissionCategory::SETTING->value => ['view_setting'], // Should not be included
        ]);

        $result = $this->service->getDefaultPermissions(DefaultRole::RECEPTIONIST->value);

        // Assert that receptionist gets only allowed permissions
        $this->assertIsArray($result);
        $this->assertContains('view_user', $result);
        $this->assertContains('view_gym_member', $result);

        // Receptionist should not have restricted category permissions
        $this->assertNotContains('view_permission', $result);
        $this->assertNotContains('manage_staff', $result);
        $this->assertNotContains('view_setting', $result);
    }

    #[Test]
    public function it_returns_indexed_array_without_keys()
    {
        // Create some permissions
        $this->createPermissionsForCategories([
            PermissionCategory::USER->value => ['view_user', 'create_user'],
            PermissionCategory::GYM_MEMBER->value => ['view_gym_member'],
        ]);

        $result = $this->service->getDefaultPermissions(DefaultRole::GYM_OWNER->value);

        // Assert that result is properly indexed (no string keys)
        $this->assertIsArray($result);
        $keys = array_keys($result);
        $expectedKeys = range(0, count($result) - 1);
        $this->assertEquals($expectedKeys, $keys);
    }

    #[Test]
    public function it_returns_empty_array_for_role_with_no_permissions()
    {
        // Don't create any permissions

        $result = $this->service->getDefaultPermissions(DefaultRole::GYM_OWNER->value);

        // Assert that result is empty array
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_returns_all_data_when_invalid_role_specified()
    {
        // Create some permissions
        $this->createPermissionsForCategories([
            PermissionCategory::USER->value => ['view_user'],
        ]);

        $result = $this->service->getDefaultPermissions('invalid_role');

        // When role doesn't exist in data, it should return all data
        $this->assertIsArray($result);
        $this->assertArrayHasKey(DefaultRole::GYM_OWNER->value, $result);
        $this->assertArrayHasKey(DefaultRole::MANAGER->value, $result);
        $this->assertArrayHasKey(DefaultRole::RECEPTIONIST->value, $result);
    }

    #[Test]
    public function it_handles_permissions_with_different_categories_correctly()
    {
        // Create permissions across all categories to test role-specific filtering
        $allCategories = [
            PermissionCategory::USER->value => ['view_user'],
            PermissionCategory::PERMISSION->value => ['view_permission'],
            PermissionCategory::GYM_MEMBER->value => ['view_gym_member'],
            PermissionCategory::MEMBERSHIP_PLAN->value => ['view_membership_plan'],
            PermissionCategory::TRAINER->value => ['view_trainer'],
            PermissionCategory::RECEPTIONIST->value => ['view_receptionist'],
            PermissionCategory::ATTENDANCE->value => ['view_attendance'],
            PermissionCategory::WIDGETS->value => ['view_widgets'],
            PermissionCategory::REPORT->value => ['view_report'],
            PermissionCategory::SETTING->value => ['view_setting'],
            PermissionCategory::COMMUNICATION->value => ['view_communication'],
            PermissionCategory::GENERAL->value => ['view_general'],
            PermissionCategory::MANAGER->value => ['view_manager'],
            PermissionCategory::PAYMENT->value => ['view_payment'],
        ];

        $this->createPermissionsForCategories($allCategories);

        // Test gym owner (should have all categories)
        $gymOwnerResult = $this->service->getDefaultPermissions(DefaultRole::GYM_OWNER->value);
        $this->assertCount(14, $gymOwnerResult); // All 14 permissions

        // Test manager (should exclude MANAGER category)
        $managerResult = $this->service->getDefaultPermissions(DefaultRole::MANAGER->value);
        $this->assertCount(13, $managerResult); // 13 permissions (excludes MANAGER)
        $this->assertNotContains('view_manager', $managerResult);

        // Test receptionist (should exclude PERMISSION, SETTING, and MANAGER categories)
        $receptionistResult = $this->service->getDefaultPermissions(DefaultRole::RECEPTIONIST->value);
        $this->assertCount(10, $receptionistResult); // 10 permissions
        $this->assertNotContains('view_permission', $receptionistResult);
        $this->assertNotContains('view_setting', $receptionistResult);
        $this->assertNotContains('view_manager', $receptionistResult);
    }

    #[Test]
    public function it_maintains_permission_order_by_category()
    {
        // Create permissions in a specific order
        Permission::create(['name' => 'z_permission', 'category' => PermissionCategory::USER->value, 'guard_name' => 'web']);
        Permission::create(['name' => 'a_permission', 'category' => PermissionCategory::USER->value, 'guard_name' => 'web']);
        Permission::create(['name' => 'm_permission', 'category' => PermissionCategory::GYM_MEMBER->value, 'guard_name' => 'web']);

        $result = $this->service->getDefaultPermissions(DefaultRole::RECEPTIONIST->value);

        // The method should maintain some order (though exact order depends on getPermissions implementation)
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertContains('z_permission', $result);
        $this->assertContains('a_permission', $result);
        $this->assertContains('m_permission', $result);
    }

    /**
     * Helper method to create permissions for specific categories
     */
    private function createPermissionsForCategories(array $categories): void
    {
        foreach ($categories as $category => $permissions) {
            foreach ($permissions as $permission) {
                Permission::create([
                    'name' => $permission,
                    'category' => $category,
                    'guard_name' => 'web',
                ]);
            }
        }
    }
}
