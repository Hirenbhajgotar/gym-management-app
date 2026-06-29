<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use App\Services\RolesAndPermissionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
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
    public function it_derives_slug_from_resource_class_name(): void
    {
        $this->assertSame('gym_member', $this->service->deriveSlugFromClassName('GymMemberResource', 'Resource'));
        $this->assertSame('membership_plan', $this->service->deriveSlugFromClassName('MembershipPlanResource', 'Resource'));
        $this->assertSame('biometric_device', $this->service->deriveSlugFromClassName('BiometricDeviceResource', 'Resource'));
    }

    #[Test]
    public function it_derives_slug_from_page_class_name(): void
    {
        $this->assertSame('dashboard', $this->service->deriveSlugFromClassName('DashboardPage', 'Page'));
        $this->assertSame('qr_code_manager', $this->service->deriveSlugFromClassName('QrCodeManagerPage', 'Page'));
        $this->assertSame('system_health', $this->service->deriveSlugFromClassName('SystemHealthPage', 'Page'));
    }

    #[Test]
    public function it_derives_slug_from_widget_class_name(): void
    {
        $this->assertSame('gym_revenue_chart', $this->service->deriveSlugFromClassName('GymRevenueChartWidget', 'Widget'));
        $this->assertSame('membership_expiry_alerts', $this->service->deriveSlugFromClassName('MembershipExpiryAlertsWidget', 'Widget'));
    }

    #[Test]
    public function it_handles_single_word_class_names(): void
    {
        $this->assertSame('payment', $this->service->deriveSlugFromClassName('Payment', 'Resource'));
        $this->assertSame('dashboard', $this->service->deriveSlugFromClassName('Dashboard', 'Page'));
        $this->assertSame('chart', $this->service->deriveSlugFromClassName('Chart', 'Widget'));
    }

    #[Test]
    public function it_handles_class_name_without_suffix(): void
    {
        $this->assertSame('gym_member', $this->service->deriveSlugFromClassName('GymMember', 'Resource'));
    }

    #[Test]
    public function it_handles_consecutive_capitals(): void
    {
        $this->assertSame('lead_kanban_board', $this->service->deriveSlugFromClassName('LeadKanbanBoardPage', 'Page'));
    }

    #[Test]
    public function it_generates_resource_permissions_with_default_actions(): void
    {
        $permissions = $this->service->generateResourcePermissions('gym_member');

        $this->assertIsArray($permissions);
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

        $this->assertIsArray($permissions);
        $this->assertNotEmpty($permissions);
        $this->assertContains('view_any_gym_member', $permissions);
        $this->assertContains('view_gym_member', $permissions);
    }

    #[Test]
    public function it_discovers_page_permissions(): void
    {
        $permissions = $this->service->discoverFilamentPermissions();

        $pagePermissions = array_filter($permissions, fn ($p) => str_starts_with($p, 'page_'));
        $this->assertNotEmpty($pagePermissions);
    }

    #[Test]
    public function it_discovers_widget_permissions(): void
    {
        $permissions = $this->service->discoverFilamentPermissions();

        $widgetPermissions = array_filter($permissions, fn ($p) => str_starts_with($p, 'widget_'));
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

        $slug = $this->service->deriveSlugFromClassName('BaseEmployeeResource', 'Resource');
        foreach (config('filament-permissions.resource_actions', []) as $action) {
            $this->assertNotContains("{$action}_{$slug}", $permissions);
        }
    }

    #[Test]
    public function it_generates_correct_permission_format_for_resources(): void
    {
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
            'page_enroll_member',
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
        $panels = config('filament-permissions.panels', []);
        $this->assertContains('admin', $panels);
        $this->assertNotContains('super_admin', $panels);
    }

    #[Test]
    public function it_has_resource_actions_configured(): void
    {
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
