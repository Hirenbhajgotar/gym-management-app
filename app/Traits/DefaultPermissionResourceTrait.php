<?php

namespace App\Traits;

use App\Models\PermissionSetting;

trait DefaultPermissionResourceTrait
{
    public ?string $permissionSettingRole = null;

    public ?PermissionSetting $permissionSetting = null;

    protected array $resourceActions = [
        'view_any',
        'view',
        'create',
        'update',
        'delete',
        'delete_any',
        'force_delete',
        'force_delete_any',
        'restore',
        'replicate',
        'reorder',
        'export',
        'import',
    ];

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
     * Checks database settings first, then falls back to dynamic generation.
     */
    public function getDefaultPermissions(): array
    {
        return isset($this->permissionSetting->default_permissions) && ! empty($this->permissionSetting->default_permissions)
            ? $this->permissionSetting->default_permissions
            : $this->getFallbackPermissions();
    }

    /**
     * Generate fallback permissions dynamically based on allowed resources.
     */
    protected function getFallbackPermissions(): array
    {
        if (! $this->permissionSettingRole) {
            return [];
        }

        $permissions = [];

        // 1. Generate permissions from allowed resources
        $resources = $this->getRoleAllowedResources($this->permissionSettingRole);
        
        foreach ($resources as $resource) {
            foreach ($this->resourceActions as $action) {
                // Determine resource name suffix (handle potential naming variations if needed)
                // Seeder uses: $action . '_' . $resource;
                $permissions[] = "{$action}_{$resource}";
            }
        }

        // 2. Add role-specific/extra permissions
        $extraPermissions = $this->getRoleSpecificPermissions($this->permissionSettingRole);
        
        $permissions = array_merge($permissions, $extraPermissions);

        return array_values(array_unique($permissions));
    }

    /**
     * Define which resources a role has FULL access to.
     * Used to generate standard CRUD permissions.
     */
    protected function getRoleAllowedResources(string $role): array
    {
        return match ($role) {
            'manager' => [
                'user',
                'trainer',
                'receptionist',
                'gym_member',
                'gym',
                'membership_plan',
                'attendance',
                'payment',
            ],
            'receptionist' => [
                'gym_member',
                // 'payment', // Payment is full CRUD in Seeder except 'delete'? No, Seeder has create, update, view. Missing delete.
                // 'attendance', // Seeder has create, view_any, view. Missing update? No. Seeder: view_any, view, create. 
                // So purely FULL CRUD resources for Receptionist are: gym_member (yes, delete is there), membership (create, update, view..).
                // Actually Seeder Receptionist has 'delete_member' (for gym_member).
                // So 'gym_member' is safe.
            ],
            'trainer' => [
                 // Trainer mostly has limited access (view/update), not full CRUD.
                 // Exceptions: workout, schedule, attendance (CRUD-ish).
                 'workout',
                 'schedule', // Seeder: view_any, view, create, update, delete.
            ],
            default => [],
        };
    }

    /**
     * Define specific permissions that don't fit the standard "Full CRUD" pattern or are widgets/pages.
     */
    protected function getRoleSpecificPermissions(string $role): array
    {
        return match ($role) {
            'manager' => [
                 // Manager Specifics (Seeder excludes some, but typically Manager needs Dashboard etc)
                 // Seeder excludes 'access_admin_panel' from Manager via logic error?
                 // But we should include access permissions.
                 'access_admin_panel',
                 'view_dashboard',
                 
                 // Widgets
                 'widget_gym_revenue_chart',
                 'widget_gym_stats_overview',
                 'widget_membership_expiry_alerts_chart',
                 'widget_recent_activity',
                 'widget_todays_birthdays',
                 'widget_trainer_performance',

                 // Pages
                 'page_dashboard',
                 'page_edit_gym_profile',
                 'page_register_gym', // Seeder might exclude this for Manager?
                 'page_settings',
                 'page_reports',
                 'page_analytics',
                 'page_backup',
                 'page_system_health',

                 // System & Communication
                 'manage_settings',
                 'view_analytics',
                 'export_data',
                 'import_data',
                 'manage_backups',
                 'view_system_logs',
                 'send_notifications',
                 'send_bulk_emails',
                 'manage_announcements',
                 'bulk_actions', // Advanced
                 'advanced_search',
                 'view_financial_reports',
                 'view_member_reports',
                 'view_trainer_reports',
                 'view_equipment_reports',
                 'export_reports',
            ],
            'receptionist' => [
                'access_admin_panel',
                'view_dashboard',
                'page_dashboard',

                // Membership Plans (View Only)
                'view_any_membership_plan',
                'view_membership_plan',
                
                // Memberships (CRUD but maybe not delete?)
                // Seeder: view_any, view, create, update. (No delete).
                'view_any_membership',
                'view_membership',
                'create_membership',
                'update_membership',

                // Payments (View/Create/Update - No delete)
                'view_any_payment',
                'view_payment',
                'create_payment',
                'update_payment',

                // Attendance (Create/View - No update/delete?)
                'view_any_attendance',
                'view_attendance',
                'create_attendance',

                // Trainers (View Only)
                'view_any_trainer',
                'view_trainer',

                // Equipment (View Only)
                'view_any_equipment',
                'view_equipment',

                // Profile
                'view_receptionist', // specific permission to view own profile? Seeder says 'view_receptionist'.
                'update_receptionist',

                // Widgets
                'widget_stats_overview',
                'widget_recent_members',

                // Comm
                'send_notifications',
            ],
            'trainer' => [
                'access_admin_panel',
                'view_dashboard',
                'page_dashboard',

                 // Members (View/Update only)
                'view_any_member', // Seeder uses 'member' suffix for permissions here?
                'view_member',
                'update_member',

                // Workouts (Full CRUD in AllowedResources? Yes)
                // Schedules (Full CRUD in AllowedResources? Yes)

                // Attendance (Create/Update/View)
                'view_any_attendance',
                'view_attendance',
                'create_attendance',
                'update_attendance',

                // Profile
                'view_trainer',
                'update_trainer',

                // Equipment (View Only)
                'view_any_equipment',
                'view_equipment',

                // Widgets
                'widget_stats_overview',
                'widget_recent_members',
            ],
            default => [],
        };
    }

    // Legacy/Helper methods (Optional: remove if not used, or keep for compatibility)
    protected function getFallbackResources(): array
    {
        return $this->getAvailableCategoriesForRole($this->permissionSettingRole ?? '')['resources'] ?? [];
    }

    protected function getFallbackWidgets(): array
    {
        return $this->getAvailableCategoriesForRole($this->permissionSettingRole ?? '')['widgets'] ?? [];
    }
    
    protected function getFallbackReports(): array
    {
        return $this->getAvailableCategoriesForRole($this->permissionSettingRole ?? '')['reports'] ?? [];
    }

    protected function getFallbackOtherResources(): array
    {
        return $this->getAvailableCategoriesForRole($this->permissionSettingRole ?? '')['other'] ?? [];
    }

    /**
     * Get all available categories for a specific role
     * @deprecated Used for legacy fallback methods
     */
    protected function getAvailableCategoriesForRole(string $role): array
    {
        $categoryMap = [
            'receptionist' => [
                'resources' => ['gym_member', 'membership_plan', 'attendance', 'payment'],
                'widgets' => ['widgets'],
                'reports' => ['report'],
                'other' => ['communication', 'setting', 'general'],
            ],
            'manager' => [
                'resources' => ['gym_member', 'membership_plan', 'trainer', 'receptionist', 'attendance', 'payment', 'equipment', 'class'],
                'widgets' => ['widgets'],
                'reports' => ['report'],
                'other' => ['communication', 'setting', 'general', 'system'],
            ],
            'trainer' => [
                'resources' => ['gym_member', 'class', 'attendance', 'equipment', 'training_session', 'workout_plan', 'nutrition_plan'],
                'widgets' => ['widgets'],
                'reports' => ['report'],
                'other' => ['communication', 'general'],
            ],
        ];

        return $categoryMap[$role] ?? [
            'resources' => ['gym_member', 'membership_plan'],
            'widgets' => ['widgets'],
            'reports' => ['report'],
            'other' => ['communication', 'setting', 'general'],
        ];
    }
}
