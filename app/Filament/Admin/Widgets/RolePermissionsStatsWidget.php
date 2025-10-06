<?php

namespace App\Filament\Admin\Widgets;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Spatie\Permission\Models\Role;

class RolePermissionsStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    // Widget configuration properties
    public ?string $role = null;

    public ?string $roleDisplayName = null;

    public ?array $statsConfig = null;

    public ?string $primaryColor = 'primary';

    public $currentRole;

    // Default configuration for different roles
    protected array $defaultConfigs = [
        'receptionist' => [
            'display_name' => 'Receptionist',
            'icon' => 'heroicon-o-user-circle',
            'primary_color' => 'info',
            'description' => 'Front desk staff permissions',
        ],
        'manager' => [
            'display_name' => 'Manager',
            'icon' => 'heroicon-o-briefcase',
            'primary_color' => 'success',
            'description' => 'Management level permissions',
        ],
        'trainer' => [
            'display_name' => 'Trainer',
            'icon' => 'heroicon-o-academic-cap',
            'primary_color' => 'warning',
            'description' => 'Training staff permissions',
        ],
    ];

    #[On('refreshRolePermissionsStats')]
    public function refreshWidget()
    {
        $this->dispatch('$refresh');
    }

    /**
     * Configure the widget for a specific role
     */
    public function configureForRole(string $role, array $customConfig = []): self
    {
        $this->role = $role;
        $defaultConfig = $this->defaultConfigs[$role] ?? [];

        $this->roleDisplayName = $customConfig['display_name'] ?? $defaultConfig['display_name'] ?? Str::title($role);
        $this->primaryColor = $customConfig['primary_color'] ?? $defaultConfig['primary_color'] ?? 'primary';

        $this->statsConfig = array_merge($defaultConfig, $customConfig);

        $this->currentRole = $this->getActiveRoleQuery()->first();

        return $this;
    }

    /**
     * Get stats for the configured role
     */
    protected function getStats(): array
    {
        $this->configureForRole($this->role);

        $currentTenant = Filament::getTenant();

        return [
            $this->getActiveUsersStats($currentTenant),
            $this->getActivePermissionsStats($currentTenant),
            $this->getResourceAccessStats($currentTenant),
            // $this->getLastUpdatedStats($currentTenant),
        ];
    }

    /**
     * Get active users count for the role
     */
    protected function getActiveUsersStats($currentTenant): Stat
    {
        $count = $this->getActiveUsersCount($currentTenant);

        return Stat::make("Active {$this->roleDisplayName}s", $count)
            ->description("Users with {$this->roleDisplayName} role")
            ->descriptionIcon($this->statsConfig['icon'] ?? 'heroicon-o-users')
            ->color($this->primaryColor)
            ->extraAttributes([
                'class' => 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors',
            ]);
    }

    /**
     * Get active permissions count for the role
     */
    protected function getActivePermissionsStats($currentTenant): Stat
    {
        $count = $this->getActivePermissionsCount($currentTenant);

        return Stat::make('Active Permissions', $count)
            ->description('Permissions assigned to role')
            ->descriptionIcon('heroicon-o-shield-check')
            ->color('success')
            ->extraAttributes([
                'class' => 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors',
            ]);
    }

    private function getActiveRoleQuery()
    {
        $currentTenant = Filament::getTenant();

        $roleQuery = Role::where('name', $this->role);

        if ($currentTenant) {
            $roleQuery->where('gym_id', $currentTenant->id);
        }

        $role = $roleQuery;

        return $role;
    }

    /**
     * Get resource access count for the role
     */
    protected function getResourceAccessStats($currentTenant): Stat
    {
        $count = $this->getResourceAccessCount($currentTenant);

        return Stat::make('Resource Access', $count)
            ->description('Accessible resources')
            ->descriptionIcon('heroicon-o-squares-2x2')
            ->color('warning')
            ->extraAttributes([
                'class' => 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors',
            ]);
    }

    /**
     * Get last updated information for the role
     */
    protected function getLastUpdatedStats($currentTenant): Stat
    {
        $lastUpdated = $this->getLastUpdated($currentTenant);

        return Stat::make('Last Updated', $lastUpdated)
            ->description('Recent permission changes')
            ->descriptionIcon('heroicon-o-clock')
            ->color('gray')
            ->extraAttributes([
                'class' => 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors',
            ]);
    }

    /**
     * Get count of active users for the role
     */
    protected function getActiveUsersCount($currentTenant): int
    {
        return User::whereHas('roles', function ($query) use ($currentTenant) {
            $query->where('name', $this->role);

            if ($currentTenant) {
                $query->where('roles.gym_id', $currentTenant->id);
            }
        })->count();
    }

    /**
     * Get count of active permissions for the role
     */
    protected function getActivePermissionsCount($currentTenant): int
    {
        // $roleQuery = Role::where('name', $this->role);

        // if ($currentTenant) {
        //     $roleQuery->where('gym_id', $currentTenant->id);
        // }

        // $role = $roleQuery->first();

        $role = $this->currentRole;

        return $role ? $role->permissions->count() : 0;
    }

    /**
     * Get count of resource access permissions for the role
     */
    protected function getResourceAccessCount($currentTenant): int
    {
        // $roleQuery = Role::where('name', $this->role);

        // if ($currentTenant) {
        //     $roleQuery->where('gym_id', $currentTenant->id);
        // }

        // $role = $roleQuery->first();
        $role = $this->currentRole;

        if (! $role) {
            return 0;
        }

        $resourcePermissions = $role->permissions->filter(function ($permission) {
            return str_contains($permission->name, 'view_') ||
                str_contains($permission->name, 'create_') ||
                str_contains($permission->name, 'edit_') ||
                str_contains($permission->name, 'delete_');
        });

        return $resourcePermissions->count();
    }

    /**
     * Get last updated time for the role
     */
    protected function getLastUpdated($currentTenant): string
    {
        $role = $this->currentRole;

        return $role && $role->updated_at ? $role->updated_at->diffForHumans() : 'Never';
    }

    /**
     * Get detailed breakdown of permissions by category
     */
    public function getPermissionBreakdown($currentTenant): array
    {
        $role = $this->currentRole;

        if (! $role) {
            return [];
        }

        $permissions = $role->permissions->pluck('name');

        $breakdown = [
            'resource_permissions' => $permissions->filter(
                fn ($p) => str_contains($p, 'view_') || str_contains($p, 'create_') ||
                    str_contains($p, 'edit_') || str_contains($p, 'delete_')
            )->count(),
            'widget_permissions' => $permissions->filter(
                fn ($p) => str_contains($p, 'widget_')
            )->count(),
            'report_permissions' => $permissions->filter(
                fn ($p) => str_contains($p, 'report_') || str_contains($p, 'export_')
            )->count(),
            'system_permissions' => $permissions->filter(
                fn ($p) => str_contains($p, 'manage_') || str_contains($p, 'system_')
            )->count(),
            'communication_permissions' => $permissions->filter(
                fn ($p) => str_contains($p, 'send_') || str_contains($p, 'notification')
            )->count(),
        ];

        return $breakdown;
    }

    /**
     * Check if the current user can access this widget
     */
    public static function canView(): bool
    {
        $allowRoles = auth()->user()->hasRole(['super_admin', 'admin', 'gym_owner']);

        $currentPanel = Filament::getCurrentPanel();

        $currentUrl = request()->url();

        // Get the actual page URL from referer when it's a Livewire request
        $actualUrl = $currentUrl;
        if (str_contains($currentUrl, '/livewire/update')) {
            $actualUrl = request()->header('referer', $currentUrl);
        }

        // In SPA mode, URL-based detection is more reliable
        $allowUrls = str_contains($actualUrl, '/permissions') ||
            str_contains($currentUrl, '/permission');

        // Only show on permissions pages in specific panels
        $allowedPanels = ['admin', 'staff'];

        $allowRoutes = in_array($currentPanel->getId(), $allowedPanels);

        return $allowRoles && $allowUrls && $allowRoutes;
    }

    /**
     * Get the widget heading
     */
    // protected function getHeading(): ?string
    // {
    //     return "{$this->roleDisplayName} Statistics";
    // }

    /**
     * Get additional widget options
     */
    protected function getOptions(): array
    {
        return [
            'id' => "role-permissions-stats-{$this->role}",
            'pollingInterval' => null,
            'isLazy' => false,
            'persistState' => true,
        ];
    }
}
