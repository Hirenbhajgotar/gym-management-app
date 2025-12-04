<?php

namespace App\Traits;

use App\Models\PermissionSetting;

trait DefaultPermissionResourceTrait
{
    private $defaultPermissionResource = null;

    public $permissionSettingRole = null;

    public $permissionSetting = null;

    protected function getDefaultResources(): array
    {
        return isset($this->defaultPermissionResource['resources']) ? $this->defaultPermissionResource['resources'] : $this->getFallbackResources();
    }

    protected function getDefaultWidgets(): array
    {
        return isset($this->defaultPermissionResource['widgets']) ? $this->defaultPermissionResource['widgets'] : $this->getFallbackWidgets();
    }

    protected function getDefaultReports(): array
    {
        return isset($this->defaultPermissionResource['reports']) ? $this->defaultPermissionResource['reports'] : $this->getFallbackReports();
    }

    protected function getOtherDefaultResource(): array
    {
        return isset($this->defaultPermissionResource['other']) ? $this->defaultPermissionResource['other'] : $this->getFallbackOtherResources();
    }

    public function setDefaultRole(?string $role = null)
    {
        $this->permissionSettingRole = $role;

        $this->permissionSetting = $this->getPermissionsSettings($this->permissionSettingRole);

        $this->defaultPermissionResource = $this->permissionSetting?->default_resources;
    }

    /**
     * Return the default permissions associated with the currently set role.
     *
     * If no role is set or no permission setting exists for the role, an empty array is returned.
     *
     * @return array<string>
     */
    public function getDefaultPermissions(): array
    {
        return $this->permissionSetting->default_permissions ?? [];
    }

    protected function getPermissionsSettings(?string $role = null): ?PermissionSetting
    {
        if (! $role) {
            return null;
        }

        return PermissionSetting::where('role', $role)->first();
    }

    // Fallback methods for when no PermissionSetting exists
    protected function getFallbackResources(): array
    {
        return match ($this->permissionSettingRole) {
            'receptionist' => [
                'gym_member',
                'membership_plan',
                'attendance',
                'payment',
            ],
            'manager' => [
                'gym_member',
                'membership_plan',
                'trainer',
                'receptionist',
                'attendance',
                'payment',
                // 'equipment',
                // 'class',
            ],
            'trainer' => [
                'gym_member',
                'attendance',
                // 'class',
                // 'equipment',
                // 'training_session',
                // 'workout_plan',
                // 'nutrition_plan',
            ],
            default => [
                'attendance',
                'gym_member',
                'membership_plan',
                'receptionist',
                'trainer',
            ]
        };
    }

    protected function getFallbackWidgets(): array
    {
        return match ($this->permissionSettingRole) {
            'receptionist' => [
                'widgets',
            ],
            'manager' => [
                'widgets',
            ],
            'trainer' => [
                'widgets',
            ],
            default => [
                'widgets',
            ]
        };
    }

    protected function getFallbackReports(): array
    {
        return match ($this->permissionSettingRole) {
            'receptionist' => [
                'report',
            ],
            'manager' => [
                'report',
            ],
            'trainer' => [
                'report',
            ],
            default => [
                'report',
            ]
        };
    }

    protected function getFallbackOtherResources(): array
    {
        return match ($this->permissionSettingRole) {
            'receptionist' => [
                'communication',
                'setting',
                'general',
            ],
            'manager' => [
                'communication',
                'setting',
                'general',
                'system',
            ],
            'trainer' => [
                'communication',
                'general',
            ],
            default => [
                'communication',
                'setting',
                'general',
            ]
        };
    }

    /**
     * Get role-specific permissions for bulk operations
     */
    protected function getRoleSpecificPermissions(string $role, string $type = 'default'): array
    {
        $permissionMap = [
            'receptionist' => [
                'default' => [
                    'view_gym_member',
                    'create_gym_member',
                    'edit_gym_member',
                    'view_membership_plan',
                    'create_membership_plan',
                    'edit_membership_plan',
                ],
                'extended' => [
                    'view_gym_member',
                    'view_any_gym_member',
                    'create_gym_member',
                    'edit_gym_member',
                    'view_membership_plan',
                    'view_any_membership_plan',
                    'create_membership_plan',
                    'edit_membership_plan',
                    'view_payment',
                    'create_payment',
                    'edit_payment',
                ],
            ],
            'manager' => [
                'default' => [
                    'view_gym_member',
                    'view_any_gym_member',
                    'create_gym_member',
                    'edit_gym_member',
                    'delete_gym_member',
                    'view_membership_plan',
                    'view_any_membership_plan',
                    'create_membership_plan',
                    'edit_membership_plan',
                    'delete_membership_plan',
                    'view_trainer',
                    'create_trainer',
                    'edit_trainer',
                ],
                'extended' => [
                    'view_gym_member',
                    'view_any_gym_member',
                    'create_gym_member',
                    'edit_gym_member',
                    'delete_gym_member',
                    'export_gym_member',
                    'view_membership_plan',
                    'view_any_membership_plan',
                    'create_membership_plan',
                    'edit_membership_plan',
                    'delete_membership_plan',
                    'view_trainer',
                    'create_trainer',
                    'edit_trainer',
                    'delete_trainer',
                    'manage_staff',
                ],
            ],
            'trainer' => [
                'default' => [
                    'view_gym_member',
                    'view_class',
                    'create_class',
                    'edit_class',
                    'manage_class_bookings',
                    'mark_attendance',
                    'view_member_progress',
                    'create_member_progress',
                    'edit_member_progress',
                ],
                'extended' => [
                    'view_gym_member',
                    'view_any_gym_member',
                    'edit_gym_member',
                    'view_class',
                    'view_any_class',
                    'create_class',
                    'edit_class',
                    'manage_class_bookings',
                    'mark_attendance',
                    'view_member_progress',
                    'create_member_progress',
                    'edit_member_progress',
                    'create_workout_plan',
                    'edit_workout_plan',
                    'assign_workout_plan',
                ],
            ],
        ];

        return $permissionMap[$role][$type] ?? [];
    }

    /**
     * Get all available categories for a specific role
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

    /**
     * Check if a permission is allowed for a specific role
     */
    protected function isPermissionAllowedForRole(string $permission, string $role): bool
    {
        $restrictedPermissions = [
            'receptionist' => [
                'delete_gym_member',
                'force_delete_gym_member',
                'delete_membership_plan',
                'manage_settings',
                'manage_backups',
                'view_financial_reports',
                'delete_trainer',
                'create_trainer',
            ],
            'trainer' => [
                'delete_gym_member',
                'force_delete_gym_member',
                'create_gym_member',
                'delete_membership_plan',
                'create_membership_plan',
                'edit_membership_plan',
                'manage_settings',
                'manage_backups',
                'view_financial_reports',
                'delete_payment',
                'create_payment',
                'edit_payment',
            ],
            'manager' => [
                // Managers have fewer restrictions
                'manage_gym_settings',
                'delete_gym',
                'transfer_ownership',
            ],
        ];

        $restricted = $restrictedPermissions[$role] ?? [];

        return ! in_array($permission, $restricted);
    }

    /**
     * Get permissions that should be auto-selected for a role
     */
    protected function getAutoSelectedPermissions(string $role): array
    {
        $autoSelect = [
            'receptionist' => [
                'view_gym_member',
                'access_notifications',
            ],
            'manager' => [
                'view_gym_member',
                'view_any_gym_member',
                'access_notifications',
                'view_analytics',
            ],
            'trainer' => [
                'view_gym_member',
                'view_class',
                'mark_attendance',
            ],
        ];

        return $autoSelect[$role] ?? [];
    }

    /**
     * Validate permission combination for a role
     */
    protected function validatePermissionCombination(array $permissions, string $role): array
    {
        $errors = [];

        // Check for required dependencies
        $dependencies = [
            'create_gym_member' => ['view_gym_member'],
            'edit_gym_member' => ['view_gym_member'],
            'delete_gym_member' => ['view_gym_member', 'edit_gym_member'],
            'create_membership_plan' => ['view_membership_plan'],
            'edit_membership_plan' => ['view_membership_plan'],
            'delete_membership_plan' => ['view_membership_plan', 'edit_membership_plan'],
        ];

        foreach ($permissions as $permission) {
            if (isset($dependencies[$permission])) {
                foreach ($dependencies[$permission] as $dependency) {
                    if (! in_array($dependency, $permissions)) {
                        $errors[] = "Permission '{$permission}' requires '{$dependency}' to be selected.";
                    }
                }
            }
        }

        // Role-specific validations
        if ($role === 'trainer') {
            if (in_array('manage_class_bookings', $permissions) && ! in_array('view_class', $permissions)) {
                $errors[] = "Trainers need 'view_class' permission to manage class bookings.";
            }
        }

        return $errors;
    }

    /**
     * Get suggested permissions based on selected ones
     */
    protected function getSuggestedPermissions(array $selectedPermissions, string $role): array
    {
        $suggestions = [];

        if (in_array('view_gym_member', $selectedPermissions) && ! in_array('create_gym_member', $selectedPermissions)) {
            $suggestions[] = 'create_gym_member';
        }

        if (in_array('create_gym_member', $selectedPermissions) && ! in_array('edit_gym_member', $selectedPermissions)) {
            $suggestions[] = 'edit_gym_member';
        }

        if (in_array('view_class', $selectedPermissions) && $role === 'trainer' && ! in_array('mark_attendance', $selectedPermissions)) {
            $suggestions[] = 'mark_attendance';
        }

        return array_filter($suggestions, fn ($permission) => $this->isPermissionAllowedForRole($permission, $role));
    }
}
