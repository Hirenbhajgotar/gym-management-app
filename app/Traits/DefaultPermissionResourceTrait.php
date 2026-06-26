<?php

namespace App\Traits;

use App\Models\PermissionSetting;
use App\Services\RolesAndPermissionsService;

/**
 * @deprecated No longer used by the permission management UI. Only retained for
 *             {@see \App\Services\RolesAndPermissionsService} seeding fallback. Will be
 *             removed once seeding switches to {@see \App\Services\PermissionDiscoveryService}.
 */
trait DefaultPermissionResourceTrait
{
    public ?string $permissionSettingRole = null;

    public ?PermissionSetting $permissionSetting = null;

    public function setDefaultRole(string $role): self
    {
        $this->permissionSettingRole = $role;

        $this->permissionSetting = PermissionSetting::where('role', $role)->first();

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getDefaultResources(): array
    {
        return isset($this->permissionSetting->default_resources) && filled($this->permissionSetting->default_resources)
            ? $this->permissionSetting->default_resources
            : $this->getFallbackResources();
    }

    /**
     * @return array<int, string>
     */
    public function getDefaultWidgets(): array
    {
        return isset($this->permissionSetting->default_widgets) && filled($this->permissionSetting->default_widgets)
            ? $this->permissionSetting->default_widgets
            : $this->getFallbackWidgets();
    }

    /**
     * @return array<int, string>
     */
    public function getDefaultReports(): array
    {
        return isset($this->permissionSetting->default_reports) && filled($this->permissionSetting->default_reports)
            ? $this->permissionSetting->default_reports
            : $this->getFallbackReports();
    }

    /**
     * @return array<int, string>
     */
    public function getOtherDefaultResource(): array
    {
        return isset($this->permissionSetting->other_default_resources) && filled($this->permissionSetting->other_default_resources)
            ? $this->permissionSetting->other_default_resources
            : $this->getFallbackOtherResources();
    }

    /**
     * Get default permissions.
     * Checks database settings first, then delegates to Service for dynamic fallback.
     *
     * @return array<int, string>
     */
    public function getDefaultPermissions(): array
    {
        // Alias usage in service: use DefaultPermissionResourceTrait { getDefaultPermissions as traitGetDefaultPermissions; }

        return isset($this->permissionSetting->default_permissions) && filled($this->permissionSetting->default_permissions)
            ? $this->permissionSetting->default_permissions
            : $this->getFallbackPermissions();
    }

    /**
     * @return array<int, string>
     */
    protected function getFallbackPermissions(): array
    {
        if (! $this->permissionSettingRole) {
            return [];
        }

        // Delegate to Service for dynamic logic
        return resolve(RolesAndPermissionsService::class)->getDynamicDefaultPermissions($this->permissionSettingRole);
    }

    // Delegate fallbacks to Service to ensure UI BasePermissionsPage receives valid categories

    /**
     * @return array<int, string>
     */
    protected function getFallbackResources(): array
    {
        if (! $this->permissionSettingRole) {
            return [];
        }

        return resolve(RolesAndPermissionsService::class)->getRoleResourceCategories($this->permissionSettingRole);
    }

    /**
     * @return array<int, string>
     */
    protected function getFallbackWidgets(): array
    {
        if (! $this->permissionSettingRole) {
            return [];
        }

        return resolve(RolesAndPermissionsService::class)->getRoleWidgetCategories($this->permissionSettingRole);
    }

    /**
     * @return array<int, string>
     */
    protected function getFallbackReports(): array
    {
        if (! $this->permissionSettingRole) {
            return [];
        }

        return resolve(RolesAndPermissionsService::class)->getRoleReportCategories($this->permissionSettingRole);
    }

    /**
     * @return array<int, string>
     */
    protected function getFallbackOtherResources(): array
    {
        if (! $this->permissionSettingRole) {
            return [];
        }

        return resolve(RolesAndPermissionsService::class)->getRoleOtherCategories($this->permissionSettingRole);
    }
}
