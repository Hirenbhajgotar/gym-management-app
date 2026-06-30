<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\HasFilamentPermissions;
use App\Enums\DefaultRole;
use App\Enums\PermissionCategory;
use App\Models\Permission;
use App\Traits\DerivesPermissionsSlug;
use App\Traits\ResolvesPolicyTableName;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionException;
use ValueError;

class PermissionDiscoveryService
{
    use DerivesPermissionsSlug;
    use ResolvesPolicyTableName;

    /**
     * Cache for discovered classes across panels.
     *
     * @var array{resources: array<string, array{class: class-string, slug: string, label: string, icon: ?string}>, pages: array<string, array{class: class-string, slug: string, label: string}>, widgets: array<string, array{class: class-string, slug: string, label: string}>}
     */
    protected array $discoveredCache = [];

    /**
     * Get all discovered resources for a specific role (admin+staff panels, deduplicated).
     *
     * @return array<string, array{label: string, icon: ?string, permissions: string[], default_permissions?: string[]}>
     */
    public function getDiscoveredResourcesForRole(DefaultRole $role): array
    {
        $discovered = $this->getDiscoveredClasses();
        $resources = [];

        foreach ($discovered['resources'] as $resource) {
            $slug = $resource['slug'];
            $class = $resource['class'];

            if (! is_a($class, HasFilamentPermissions::class, true)) {
                continue;
            }

            $instance = $this->safeInstance($class);
            if ($instance === null) {
                continue;
            }

            $defaultPerms = $instance->defaultRolePermissions();
            $roleValue = $role->value;

            if (! isset($defaultPerms[$roleValue]) || blank($defaultPerms[$roleValue])) {
                continue;
            }

            $defaultActions = $defaultPerms[$roleValue];
            /** @var array<int, string> $allActions */
            $allActions = config('filament-permissions.resource_actions', []);

            $allPermissionNames = array_map(
                fn (string $action) => "{$action}_{$slug}",
                $allActions,
            );

            $defaultPermissionNames = array_map(
                fn (string $action) => "{$action}_{$slug}",
                $defaultActions,
            );

            $resources[$slug] = [
                'label' => $resource['label'],
                'icon' => $resource['icon'],
                'permissions' => $allPermissionNames,
                'default_permissions' => $defaultPermissionNames,
            ];
        }

        return $resources;
    }

    /**
     * Get all discovered pages for a role.
     *
     * @return array<string, array{label: string, permissions: string[]}>
     */
    public function getDiscoveredPagesForRole(DefaultRole $role): array
    {
        $discovered = $this->getDiscoveredClasses();
        $pages = [];

        foreach ($discovered['pages'] as $page) {
            $slug = $page['slug'];
            $class = $page['class'];

            if (! is_a($class, HasFilamentPermissions::class, true)) {
                continue;
            }

            $instance = $this->safeInstance($class);
            if ($instance === null) {
                continue;
            }

            $defaultPerms = $instance->defaultRolePermissions();
            $roleValue = $role->value;

            if (! isset($defaultPerms[$roleValue]) || blank($defaultPerms[$roleValue])) {
                continue;
            }

            $pages[$slug] = [
                'label' => $page['label'],
                'permissions' => [$slug],
            ];
        }

        return $pages;
    }

    /**
     * Get all discovered widgets for a role.
     *
     * @return array<string, array{label: string, permissions: string[]}>
     */
    public function getDiscoveredWidgetsForRole(DefaultRole $role): array
    {
        $discovered = $this->getDiscoveredClasses();
        $widgets = [];

        foreach ($discovered['widgets'] as $widget) {
            $slug = $widget['slug'];
            $class = $widget['class'];

            if (! is_a($class, HasFilamentPermissions::class, true)) {
                continue;
            }

            $instance = $this->safeInstance($class);
            if ($instance === null) {
                continue;
            }

            $defaultPerms = $instance->defaultRolePermissions();
            $roleValue = $role->value;

            if (! isset($defaultPerms[$roleValue]) || blank($defaultPerms[$roleValue])) {
                continue;
            }

            $widgets[$slug] = [
                'label' => $widget['label'],
                'permissions' => [$slug],
            ];
        }

        return $widgets;
    }

    /**
     * Get "other" permissions not matching any discovered class.
     *
     * @param  Collection<int, Permission>  $allRolePermissions
     * @return array<string, array{label: string, icon: string, permissions: string[]}>
     */
    public function getOtherPermissions(DefaultRole $role, Collection $allRolePermissions): array
    {
        $discovered = $this->getDiscoveredClasses();
        /** @var array<int, string> $resourceActions */
        $resourceActions = config('filament-permissions.resource_actions', []);

        /** @var array<string, true> $discoveredPermNames */
        $discoveredPermNames = [];

        foreach ($discovered['resources'] as $resource) {
            $slug = $resource['slug'];
            foreach ($resourceActions as $action) {
                $discoveredPermNames["{$action}_{$slug}"] = true;
            }
        }

        foreach ($discovered['pages'] as $page) {
            // Slug already includes page_ prefix from derivePageSlug()
            $discoveredPermNames[$page['slug']] = true;
        }

        foreach ($discovered['widgets'] as $widget) {
            // Slug already includes widget_ prefix from deriveWidgetSlug()
            $discoveredPermNames[$widget['slug']] = true;
        }

        /** @var array<int, Permission> $otherPerms */
        $otherPerms = [];
        foreach ($allRolePermissions as $permission) {
            if (! isset($discoveredPermNames[$permission->name])) {
                $otherPerms[] = $permission;
            }
        }

        /** @var array<string, array{label: string, icon: string, permissions: string[]}> $grouped */
        $grouped = [];
        foreach ($otherPerms as $permission) {
            $category = $permission->category instanceof PermissionCategory ? $permission->category->value : 'general';

            try {
                $catEnum = PermissionCategory::from($category);
                $label = $catEnum->getLabel();
                $icon = $catEnum->getIcon();
            } catch (ValueError) {
                $label = ucfirst(str_replace('_', ' ', $category));
                $icon = 'heroicon-o-folder';
            }

            if (! isset($grouped[$category])) {
                $grouped[$category] = [
                    'label' => $label,
                    'icon' => $icon,
                    'permissions' => [],
                ];
            }

            $grouped[$category]['permissions'][] = $permission->name;
        }

        return $grouped;
    }

    /**
     * Main entry point: returns all grouped permissions for the UI.
     *
     * @param  Collection<int, Permission>  $allRolePermissions
     * @return array{resources: array<string, array{label: string, icon: ?string, permissions: string[], default_permissions?: string[]}>, pages: array<string, array{label: string, permissions: string[]}>, widgets: array<string, array{label: string, permissions: string[]}>, other: array<string, array{label: string, icon: string, permissions: string[]}>}
     */
    public function getGroupedPermissions(DefaultRole $role, Collection $allRolePermissions): array
    {
        return [
            'resources' => $this->getDiscoveredResourcesForRole($role),
            'pages' => $this->getDiscoveredPagesForRole($role),
            'widgets' => $this->getDiscoveredWidgetsForRole($role),
            'other' => $this->getOtherPermissions($role, $allRolePermissions),
        ];
    }

    /**
     * Get ALL default permission names for a role (from all discovered classes' defaultRolePermissions()).
     *
     * @return Collection<int, Permission>
     */
    public function getDefaultPermissionsForRole(DefaultRole $role): Collection
    {
        $discovered = $this->getDiscoveredClasses();
        /** @var list<string> $permissionNames */
        $permissionNames = [];
        $roleValue = $role->value;

        foreach ($discovered['resources'] as $resource) {
            $class = $resource['class'];
            $slug = $resource['slug'];

            if (! is_a($class, HasFilamentPermissions::class, true)) {
                continue;
            }

            $instance = $this->safeInstance($class);
            if ($instance === null) {
                continue;
            }

            $defaultPerms = $instance->defaultRolePermissions();
            if (isset($defaultPerms[$roleValue]) && filled($defaultPerms[$roleValue])) {
                foreach ($defaultPerms[$roleValue] as $action) {
                    $permissionNames[] = "{$action}_{$slug}";
                }
            }
        }

        foreach ($discovered['pages'] as $page) {
            $class = $page['class'];
            $slug = $page['slug'];

            if (! is_a($class, HasFilamentPermissions::class, true)) {
                continue;
            }

            $instance = $this->safeInstance($class);
            if ($instance === null) {
                continue;
            }

            $defaultPerms = $instance->defaultRolePermissions();
            if (isset($defaultPerms[$roleValue]) && filled($defaultPerms[$roleValue])) {
                // Slug already includes page_ prefix from derivePageSlug()
                $permissionNames[] = $slug;
            }
        }

        foreach ($discovered['widgets'] as $widget) {
            $class = $widget['class'];
            $slug = $widget['slug'];

            if (! is_a($class, HasFilamentPermissions::class, true)) {
                continue;
            }

            $instance = $this->safeInstance($class);
            if ($instance === null) {
                continue;
            }

            $defaultPerms = $instance->defaultRolePermissions();
            if (isset($defaultPerms[$roleValue]) && filled($defaultPerms[$roleValue])) {
                // Slug already includes widget_ prefix from deriveWidgetSlug()
                $permissionNames[] = $slug;
            }
        }

        return Permission::query()->whereIn('name', array_unique($permissionNames))->get();
    }

    /**
     * Resolve the guard name for a given role.
     */
    public function getGuardForRole(DefaultRole $role): string
    {
        return config('filament-permissions.guard_name', 'web');
    }

    /**
     * Get all discovered classes from admin + staff panels (deduplicated).
     *
     * @return array{resources: array<string, array{class: class-string, slug: string, label: string, icon: ?string}>, pages: array<string, array{class: class-string, slug: string, label: string}>, widgets: array<string, array{class: class-string, slug: string, label: string}>}
     */
    protected function getDiscoveredClasses(): array
    {
        if (filled($this->discoveredCache)) {
            return $this->discoveredCache;
        }

        /** @var array<int, string> $panelIds */
        $panelIds = config('filament-permissions.permissions_panels', []);
        /** @var array{resources: list<string>, pages: list<string>, widgets: list<string>} $seen */
        $seen = ['resources' => [], 'pages' => [], 'widgets' => []];

        /** @var array<string, array{class: class-string, slug: string, label: string, icon: ?string}> $resources */
        $resources = [];
        /** @var array<string, array{class: class-string, slug: string, label: string}> $pages */
        $pages = [];
        /** @var array<string, array{class: class-string, slug: string, label: string}> $widgets */
        $widgets = [];

        foreach (Filament::getPanels() as $id => $panel) {
            if (! in_array($id, $panelIds, true)) {
                continue;
            }

            /** @var array<int, class-string> $panelResources */
            $panelResources = $panel->getResources();
            foreach ($panelResources as $resourceClass) {
                $reflection = $this->safeReflection($resourceClass);
                if ($reflection === null || $reflection->isAbstract()) {
                    continue;
                }

                if (! $reflection->implementsInterface(HasFilamentPermissions::class)) {
                    continue;
                }

                $className = $reflection->getShortName();
                if (in_array($className, $seen['resources'], true)) {
                    continue;
                }
                $seen['resources'][] = $className;

                $slug = $this->deriveResourceSlug($reflection);
                $label = $this->deriveLabel($slug, 'Resource');
                $icon = $this->deriveResourceIcon($reflection);

                $resources[$className] = [
                    'class' => $resourceClass,
                    'slug' => $slug,
                    'label' => $label,
                    'icon' => $icon,
                ];
            }

            /** @var array<int, class-string> $panelPages */
            $panelPages = $panel->getPages();
            foreach ($panelPages as $pageClass) {
                $reflection = $this->safeReflection($pageClass);
                if ($reflection === null || $reflection->isAbstract()) {
                    continue;
                }

                if (! $reflection->implementsInterface(HasFilamentPermissions::class)) {
                    continue;
                }

                $className = $reflection->getShortName();
                if (in_array($className, $seen['pages'], true)) {
                    continue;
                }
                $seen['pages'][] = $className;

                $slug = $this->derivePageSlug($reflection);
                $pagePrefix = config('filament-permissions.page_prefix', 'page_');
                $label = $this->deriveLabel(
                    substr($slug, strlen($pagePrefix)),
                    'Page',
                );

                $pages[$className] = [
                    'class' => $pageClass,
                    'slug' => $slug,
                    'label' => $label,
                ];
            }

            /** @var array<int, class-string> $panelWidgets */
            $panelWidgets = $panel->getWidgets();
            foreach ($panelWidgets as $widgetClass) {
                $reflection = $this->safeReflection($widgetClass);
                if ($reflection === null || $reflection->isAbstract()) {
                    continue;
                }

                if (! $reflection->implementsInterface(HasFilamentPermissions::class)) {
                    continue;
                }

                $className = $reflection->getShortName();
                if (in_array($className, $seen['widgets'], true)) {
                    continue;
                }
                $seen['widgets'][] = $className;

                $slug = $this->deriveWidgetSlug($reflection);
                $widgetPrefix = config('filament-permissions.widget_prefix', 'widget_');
                $label = $this->deriveLabel(
                    substr($slug, strlen($widgetPrefix)),
                    'Widget',
                );

                $widgets[$className] = [
                    'class' => $widgetClass,
                    'slug' => $slug,
                    'label' => $label,
                ];
            }

            // Scan cluster widget directories — cluster widgets are not registered
            // as panel-level widgets but may implement HasFilamentPermissions.
            $panelName = ucfirst($id);
            $clusterPath = app_path("Filament/{$panelName}/Clusters");

            if (is_dir($clusterPath)) {
                /** @var array<int, string> $clusterDirs */
                $clusterDirs = glob($clusterPath . '/*', GLOB_ONLYDIR) ?: [];

                foreach ($clusterDirs as $clusterDir) {
                    $widgetDir = $clusterDir . '/Widgets';

                    if (! is_dir($widgetDir)) {
                        continue;
                    }

                    $clusterName = basename($clusterDir);
                    $namespace = "App\\Filament\\{$panelName}\\Clusters\\{$clusterName}\\Widgets";

                    /** @var array<int, string> $widgetFiles */
                    $widgetFiles = glob($widgetDir . '/*.php') ?: [];

                    foreach ($widgetFiles as $file) {
                        $className = $namespace . '\\' . pathinfo($file, PATHINFO_FILENAME);

                        $reflection = $this->safeReflection($className);
                        if ($reflection === null || $reflection->isAbstract()) {
                            continue;
                        }

                        if (! $reflection->implementsInterface(HasFilamentPermissions::class)) {
                            continue;
                        }

                        $shortName = $reflection->getShortName();
                        if (in_array($shortName, $seen['widgets'], true)) {
                            continue;
                        }
                        $seen['widgets'][] = $shortName;

                        $slug = $this->deriveWidgetSlug($reflection);
                        $widgetPrefix = config('filament-permissions.widget_prefix', 'widget_');
                        $label = $this->deriveLabel(
                            substr($slug, strlen($widgetPrefix)),
                            'Widget',
                        );

                        $widgets[$shortName] = [
                            'class' => $className,
                            'slug' => $slug,
                            'label' => $label,
                        ];
                    }
                }
            }
        }

        $this->discoveredCache = [
            'resources' => $resources,
            'pages' => $pages,
            'widgets' => $widgets,
        ];

        return $this->discoveredCache;
    }

    /**
     * Safely instantiate a class without calling constructor.
     *
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    protected function safeInstance(string $class): ?object
    {
        try {
            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract()) {
                return null;
            }

            /** @var T */
            return $reflection->newInstanceWithoutConstructor();
        } catch (ReflectionException) {
            return null;
        }
    }

    /**
     * Derive resource slug from the model's policy $tableName or class name.
     *
     * @param  ReflectionClass<object>  $reflection
     */
    protected function deriveResourceSlug(ReflectionClass $reflection): string
    {
        $modelClass = $this->resolveResourceModel($reflection);

        return $this->deriveSlugFromModelOrResource($modelClass, $reflection);
    }

    /**
     * Derive a human-readable label from a slug.
     */
    protected function deriveLabel(string $slug, string $suffix): string
    {
        $humanized = ucwords(str_replace('_', ' ', $slug));

        return "{$humanized}";
    }

    /**
     * Derive icon from resource's getNavigationIcon() method.
     *
     * @param  ReflectionClass<object>  $reflection
     */
    protected function deriveResourceIcon(ReflectionClass $reflection): ?string
    {
        try {
            if ($reflection->hasMethod('getNavigationIcon')) {
                $method = $reflection->getMethod('getNavigationIcon');
                $instance = $reflection->newInstanceWithoutConstructor();

                /** @var string|null */
                return $method->invoke($instance);
            }
        } catch (ReflectionException) {
        }

        return null;
    }

    /**
     * Convert a PascalCase or camelCase string to snake_case.
     */
    protected function convertToSnakeCase(string $value): string
    {
        $value = str_replace('-', '_', $value);
        $value = preg_replace('/([A-Z])/', '_$1', $value) ?? $value;
        $value = strtolower(trim($value, '_'));

        return preg_replace('/_+/', '_', $value) ?? $value;
    }
}
