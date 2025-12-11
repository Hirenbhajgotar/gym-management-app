<?php

namespace App\Traits;

use App\Models\PermissionSetting;
use App\Services\RolesAndPermissionsService;

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

    public function getDefaultResources(): array
    {
        return isset($this->permissionSetting->default_resources) && ! empty($this->permissionSetting->default_resources)
            ? $this->permissionSetting->default_resources
            : $this->getFallbackResources();
    }

    public function getDefaultWidgets(): array
    {
        return isset($this->permissionSetting->default_widgets) && ! empty($this->permissionSetting->default_widgets)
            ? $this->permissionSetting->default_widgets
            : $this->getFallbackWidgets();
    }

    public function getDefaultReports(): array
    {
        return isset($this->permissionSetting->default_reports) && ! empty($this->permissionSetting->default_reports)
            ? $this->permissionSetting->default_reports
            : $this->getFallbackReports();
    }

    public function getOtherDefaultResource(): array
    {
        return isset($this->permissionSetting->other_default_resources) && ! empty($this->permissionSetting->other_default_resources)
            ? $this->permissionSetting->other_default_resources
            : $this->getFallbackOtherResources();
    }

    /**
     * Get default permissions.
     * Checks database settings first, then delegates to Service for dynamic fallback.
     */
    public function getDefaultPermissions(): array
    {
        //Alias usage in service: use DefaultPermissionResourceTrait { getDefaultPermissions as traitGetDefaultPermissions; }
        
        return isset($this->permissionSetting->default_permissions) && ! empty($this->permissionSetting->default_permissions)
            ? $this->permissionSetting->default_permissions
            : $this->getFallbackPermissions();
    }
    
    protected function getFallbackPermissions(): array
    {
        if (! $this->permissionSettingRole) {
            return [];
        }

        // Delegate to Service for dynamic logic
        return app(RolesAndPermissionsService::class)->getDynamicDefaultPermissions($this->permissionSettingRole);
    }


    // Delegate fallbacks to Service to ensure UI BasePermissionsPage receives valid categories
    
    protected function getFallbackResources(): array
    {
        if (! $this->permissionSettingRole) {
            return [];
        }
        return app(RolesAndPermissionsService::class)->getRoleResourceCategories($this->permissionSettingRole);
    }

    protected function getFallbackWidgets(): array
    {
        if (! $this->permissionSettingRole) {
            return [];
        }
        return app(RolesAndPermissionsService::class)->getRoleWidgetCategories($this->permissionSettingRole);
    }
    
    protected function getFallbackReports(): array
    {
        if (! $this->permissionSettingRole) {
            return [];
        }
        return app(RolesAndPermissionsService::class)->getRoleReportCategories($this->permissionSettingRole);
    }

    protected function getFallbackOtherResources(): array
    {
        if (! $this->permissionSettingRole) {
            return [];
        }
        return app(RolesAndPermissionsService::class)->getRoleOtherCategories($this->permissionSettingRole);
    }
}
