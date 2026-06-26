<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use App\Services\PermissionDiscoveryService;
use Spatie\Permission\Models\Role;
use App\Enums\DefaultRole;
use App\Filament\Admin\Clusters\Permissions\Pages\ManagerPermissionsPage;
use App\Filament\Admin\Clusters\Permissions\Pages\ReceptionistPermissionsPage;
use App\Models\Permission;
use Filament\Facades\Filament;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;

class AutoDiscoverPermissionsTest extends PermissionsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->gymOwner);

        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);
        Filament::setTenant($this->gym);
    }

    #[Test]
    public function manager_permissions_page_loads(): void
    {
        $this->actingAs($this->gymOwner);

        Livewire::test(ManagerPermissionsPage::class)
            ->assertSuccessful();
    }

    #[Test]
    public function receptionist_permissions_page_loads(): void
    {
        $this->actingAs($this->gymOwner);

        Livewire::test(ReceptionistPermissionsPage::class)
            ->assertSuccessful();
    }

    #[Test]
    public function manager_page_shows_resource_sections(): void
    {
        $this->actingAs($this->gymOwner);

        $service = resolve(PermissionDiscoveryService::class);
        $resources = $service->getDiscoveredResourcesForRole(DefaultRole::MANAGER);

        $component = Livewire::test(ManagerPermissionsPage::class);

        foreach (array_slice(array_keys($resources), 0, 3) as $slug) {
            $sectionLabel = ucwords(str_replace('_', ' ', $slug));
            $component->assertSee($sectionLabel);
        }
    }

    #[Test]
    public function receptionist_page_hides_manager_only_sections(): void
    {
        $this->actingAs($this->gymOwner);

        $managerService = resolve(PermissionDiscoveryService::class);
        $managerResources = $managerService->getDiscoveredResourcesForRole(DefaultRole::MANAGER);
        $receptionistResources = $managerService->getDiscoveredResourcesForRole(DefaultRole::RECEPTIONIST);

        $managerOnlySlugs = array_diff(array_keys($managerResources), array_keys($receptionistResources));

        $component = Livewire::test(ReceptionistPermissionsPage::class);

        // Manager-only resources should NOT have form fields on the receptionist page.
        // The form data keys follow the pattern "{slug}_permissions".
        $formDataKeys = array_keys($component->get('data'));
        foreach ($managerOnlySlugs as $slug) {
            $fieldName = $slug . '_permissions';
            $this->assertNotContains(
                $fieldName,
                $formDataKeys,
                "Manager-only resource '{$slug}' should not have a form field on receptionist page"
            );
        }
    }

    #[Test]
    public function save_permissions_persists_to_database(): void
    {
        $this->actingAs($this->gymOwner);

        $service = resolve(PermissionDiscoveryService::class);
        $resources = $service->getDiscoveredResourcesForRole(DefaultRole::MANAGER);

        if (blank($resources)) {
            $this->markTestSkipped('No discovered resources for manager role');
        }

        $firstResource = reset($resources);
        $permToCheck = $firstResource['permissions'][0] ?? null;
        $resourceKey = array_key_first($resources);

        if ($permToCheck === null) {
            $this->markTestSkipped('No permissions found for manager resources');
        }

        Permission::firstOrCreate(
            ['name' => $permToCheck, 'guard_name' => 'web']
        );

        Livewire::test(ManagerPermissionsPage::class)
            ->set('data', array_merge(
                array_fill_keys(array_keys($resources), []),
                [$resourceKey . '_permissions' => [$permToCheck]]
            ))
            ->call('save');

        // The save() method may silently fail if setPermissionsTeamId is missing.
        // Verify the permission-to-role assignment via raw DB to avoid Spatie cache issues.
        setPermissionsTeamId($this->gymId);

        $role = Role::where('name', 'manager')
            ->where('gym_id', $this->gymId)
            ->first();

        // Reload to get fresh data from DB
        $role->refresh();

        $this->assertContains(
            $permToCheck,
            $role->permissions->pluck('name')->toArray(),
            "Permission '{$permToCheck}' should be assigned to manager role after save. Role ID: {$role->id}"
        );
    }

    #[Test]
    public function reset_to_default_restores_permissions(): void
    {
        $this->actingAs($this->gymOwner);

        $service = resolve(PermissionDiscoveryService::class);
        $resources = $service->getDiscoveredResourcesForRole(DefaultRole::MANAGER);

        if (blank($resources)) {
            $this->markTestSkipped('No discovered resources for manager role');
        }

        $firstResource = reset($resources);
        $permToCheck = $firstResource['permissions'][0] ?? null;

        if ($permToCheck === null) {
            $this->markTestSkipped('No permissions found for manager resources');
        }

        Permission::firstOrCreate(
            ['name' => $permToCheck, 'guard_name' => 'web']
        );

        Livewire::test(ManagerPermissionsPage::class)
            ->set('data', array_merge(
                array_fill_keys(array_keys($resources), []),
                [array_key_first($resources) . '_permissions' => [$permToCheck]]
            ))
            ->call('save');

        Livewire::test(ManagerPermissionsPage::class)
            ->callAction('reset_permissions');

        // Clear Spatie permission cache so relationship reflects the fresh DB state
        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        $defaultPerms = $service->getDefaultPermissionsForRole(DefaultRole::MANAGER);
        $role = Role::where('name', 'manager')
            ->where('gym_id', $this->gymId)
            ->first();

        // Force reload permissions relationship from DB (avoids Spatie findByName cache issues)
        $role->load('permissions');
        $rolePermissionNames = $role->permissions->pluck('name')->toArray();

        foreach ($defaultPerms as $defaultPerm) {
            $this->assertContains(
                $defaultPerm->name,
                $rolePermissionNames,
                "Default permission '{$defaultPerm->name}' should be restored after reset"
            );
        }
    }

    #[Test]
    public function other_permissions_section_shows_unmatched_permissions(): void
    {
        $this->actingAs($this->gymOwner);

        $customPermName = 'custom_unmatched_permission_' . fake()->unique()->numerify();
        $permission = Permission::firstOrCreate(
            ['name' => $customPermName, 'guard_name' => 'web']
        );

        $role = Role::firstOrCreate(
            ['name' => 'manager', 'guard_name' => 'web', 'gym_id' => $this->gymId]
        );
        $role->givePermissionTo($permission);

        $service = resolve(PermissionDiscoveryService::class);
        $otherPerms = $service->getOtherPermissions(
            DefaultRole::MANAGER,
            $role->permissions
        );

        $foundInOther = false;
        foreach ($otherPerms as $group) {
            if (in_array($customPermName, $group['permissions'], true)) {
                $foundInOther = true;
                break;
            }
        }

        $this->assertTrue(
            $foundInOther,
            "Unmatched permission '{$customPermName}' should appear in 'Other' section"
        );
    }

    #[Test]
    public function widgets_tab_shows_discovered_widgets(): void
    {
        $this->actingAs($this->gymOwner);

        $service = resolve(PermissionDiscoveryService::class);
        $widgets = $service->getDiscoveredWidgetsForRole(DefaultRole::MANAGER);

        $component = Livewire::test(ManagerPermissionsPage::class);

        // Widgets are now rendered in a single combined section with a
        // CheckboxList. Verify the permission names appear as option labels.
        if (filled($widgets)) {
            $firstWidget = reset($widgets);
            foreach ($firstWidget['permissions'] as $permName) {
                $component->assertSee(substr(ucfirst(str_replace('_', ' ', $permName)), strlen('widget_')));
            }
        }

        $component->assertSee('Widgets');
    }

    #[Test]
    public function pages_tab_shows_discovered_pages(): void
    {
        $this->actingAs($this->gymOwner);

        $service = resolve(PermissionDiscoveryService::class);
        $pages = $service->getDiscoveredPagesForRole(DefaultRole::MANAGER);

        $component = Livewire::test(ManagerPermissionsPage::class);

        // Pages are now rendered in a single combined section with a
        // CheckboxList. Verify the permission names appear as option labels.
        if (filled($pages)) {
            $firstPage = reset($pages);
            foreach ($firstPage['permissions'] as $permName) {
                $component->assertSee(substr(ucfirst(str_replace('_', ' ', $permName)), strlen('page_')));
            }
        }

        $component->assertSee('Pages');
    }
}
