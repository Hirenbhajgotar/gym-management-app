<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use App\Filament\Admin\Resources\GymMemberResource;
use App\Models\GymMember;
use App\Services\RolesAndPermissionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class FilamentPermissionDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected RolesAndPermissionsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RolesAndPermissionsService();
    }

    #[Test]
    public function it_derives_slug_from_model_or_resource(): void
    {
        $reflection = new ReflectionClass(GymMemberResource::class);
        $this->assertSame('gym_member', $this->service->deriveSlugFromModelOrResource(GymMember::class, $reflection));
    }

    #[Test]
    public function it_generates_resource_permissions_with_default_actions(): void
    {
        $permissions = $this->service->generateResourcePermissions('gym_member');

        $this->assertNotEmpty($permissions);

        foreach ($permissions as $permission) {
            $this->assertIsString($permission);
            $this->assertStringEndsWith('_gym_member', $permission);
        }
    }

    #[Test]
    public function it_generates_resource_permissions_with_custom_actions(): void
    {
        $actions = ['view', 'create', 'update'];
        $permissions = $this->service->generateResourcePermissions('payment', $actions);

        $this->assertEqualsCanonicalizing([
            'view_payment',
            'create_payment',
            'update_payment',
        ], $permissions);
    }

    #[Test]
    public function it_generates_resource_permissions_with_all_default_actions(): void
    {
        $permissions = $this->service->generateResourcePermissions('trainer');

        /** @var array<int, string> $expectedActions */
        $expectedActions = config('filament-permissions.resource_actions', []);
        $this->assertCount(count($expectedActions), $permissions);

        foreach ($expectedActions as $action) {
            $this->assertContains("{$action}_trainer", $permissions);
        }
    }

    #[Test]
    public function it_generates_page_permission_with_prefix(): void
    {
        $permission = $this->service->generatePagePermission('dashboard');
        $this->assertSame('page_dashboard', $permission);
    }

    #[Test]
    public function it_generates_page_permission_for_complex_slug(): void
    {
        $permission = $this->service->generatePagePermission('qr_code_manager');
        $this->assertSame('page_qr_code_manager', $permission);
    }

    #[Test]
    public function it_generates_widget_permission_with_prefix(): void
    {
        $permission = $this->service->generateWidgetPermission('gym_revenue_chart');
        $this->assertSame('widget_gym_revenue_chart', $permission);
    }

    #[Test]
    public function it_generates_widget_permission_for_complex_slug(): void
    {
        $permission = $this->service->generateWidgetPermission('membership_expiry_alerts');
        $this->assertSame('widget_membership_expiry_alerts', $permission);
    }

    #[Test]
    public function it_discovers_permissions_from_filament_panels(): void
    {
        $permissions = $this->service->discoverFilamentPermissions();

        $this->assertNotEmpty($permissions);
        $this->assertContains('view_any_gym_member', $permissions);
        $this->assertContains('view_gym_member', $permissions);
    }

    #[Test]
    public function it_discovers_page_permissions(): void
    {
        /** @var array<int, string> $permissions */
        $permissions = $this->service->discoverFilamentPermissions();

        $pagePermissions = array_filter($permissions, fn (string $p): bool => str_starts_with($p, 'page_'));
        $this->assertNotEmpty($pagePermissions);
    }

    #[Test]
    public function it_discovers_widget_permissions(): void
    {
        /** @var array<int, string> $permissions */
        $permissions = $this->service->discoverFilamentPermissions();

        $widgetPermissions = array_filter($permissions, fn (string $p): bool => str_starts_with($p, 'widget_'));
        $this->assertNotEmpty($widgetPermissions);
    }

    #[Test]
    public function it_returns_unique_permissions(): void
    {
        $permissions = $this->service->discoverFilamentPermissions();

        $this->assertSame($permissions, array_values(array_unique($permissions)));
    }

    #[Test]
    public function it_skips_classes_without_has_filament_permissions_interface(): void
    {
        $permissions = $this->service->discoverFilamentPermissions();

        $this->assertNotContains('page_dashboard', $permissions);
    }

    #[Test]
    public function it_skips_abstract_classes(): void
    {
        $permissions = $this->service->discoverFilamentPermissions();

        $slug = 'base_employee';
        /** @var array<int, string> $resourceActions */
        $resourceActions = config('filament-permissions.resource_actions', []);
        foreach ($resourceActions as $action) {
            $this->assertNotContains("{$action}_{$slug}", $permissions);
        }
    }

    #[Test]
    public function it_generates_correct_permission_format_for_resources(): void
    {
        /** @var array<int, string> $permissions */
        $permissions = $this->service->discoverFilamentPermissions();

        foreach ($permissions as $permission) {
            if (str_starts_with($permission, 'page_') || str_starts_with($permission, 'widget_')) {
                continue;
            }

            $parts = explode('_', $permission, 2);
            $this->assertCount(2, $parts, "Resource permission '{$permission}' should have format action_slug");
            $this->assertNotEmpty($parts[0]);
            $this->assertNotEmpty($parts[1]);
        }
    }

    #[Test]
    public function it_generates_permissions_for_known_resources(): void
    {
        $permissions = $this->service->discoverFilamentPermissions();

        $expectedResources = [
            'gym_member',
            'membership_plan',
            'trainer',
            'lead',
            'biometric_device',
            'expense',
            'expense_budget',
            'expense_category',
        ];

        foreach ($expectedResources as $resource) {
            $this->assertContains("view_any_{$resource}", $permissions);
        }
    }

    #[Test]
    public function it_generates_permissions_for_known_pages(): void
    {
        $permissions = $this->service->discoverFilamentPermissions();

        $expectedPages = [
            'page_expense_reports',
            'page_reports_dashboard',
        ];

        foreach ($expectedPages as $pagePermission) {
            $this->assertContains($pagePermission, $permissions);
        }
    }

    #[Test]
    public function it_generates_permissions_for_known_widgets(): void
    {
        $permissions = $this->service->discoverFilamentPermissions();

        $expectedWidgets = [
            'widget_gym_revenue_chart',
            'widget_collection_stats',
            'widget_expense_stats',
            'widget_recent_expenses',
            'widget_budget_overview',
        ];

        foreach ($expectedWidgets as $widgetPermission) {
            $this->assertContains($widgetPermission, $permissions);
        }
    }

    #[Test]
    public function it_uses_config_panel_ids_for_filtering(): void
    {
        /** @var array<int, string> $panels */
        $panels = config('filament-permissions.panels', []);
        $this->assertContains('admin', $panels);
        $this->assertNotContains('super_admin', $panels);
    }

    #[Test]
    public function it_has_resource_actions_configured(): void
    {
        /** @var array<int, string> $actions */
        $actions = config('filament-permissions.resource_actions', []);
        $this->assertNotEmpty($actions);
        $this->assertContains('view_any', $actions);
        $this->assertContains('create', $actions);
        $this->assertContains('update', $actions);
        $this->assertContains('delete', $actions);
    }

    #[Test]
    public function it_has_correct_prefixes_configured(): void
    {
        $this->assertSame('page_', config('filament-permissions.page_prefix'));
        $this->assertSame('widget_', config('filament-permissions.widget_prefix'));
    }
}
