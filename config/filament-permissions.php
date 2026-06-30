<?php

declare(strict_types=1);

/**
 * Filament Permission Auto-Discovery Configuration
 *
 * Controls how {@see \App\Services\RolesAndPermissionsService::discoverFilamentPermissions()}
 * scans Filament panels and generates Spatie Permission entries.
 *
 * @see \App\Contracts\HasFilamentPermissions
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Panels to Scan
    |--------------------------------------------------------------------------
    |
    | List of Filament panel IDs whose Resources, Pages, and Widgets should
    | be scanned for HasFilamentPermissions implementations.
    |
    | The 'staff' panel shares admin resources, so only admin panel permissions
    | are discovered. Staff panel uses the same permissions via role assignment.
    |
    */
    'panels' => ['super-admin', 'admin'],

    /*
    |--------------------------------------------------------------------------
    | Panels to Scan for owners to manager permissions
    |--------------------------------------------------------------------------
    |
    | List of Filament panel IDs whose Resources, Pages, and Widgets should
    | be scanned for HasFilamentPermissions implementations.
    |
    | The 'staff' panel shares admin resources, so only admin panel permissions
    | are discovered. Staff panel uses the same permissions via role assignment.
    |
    | This panel is used for owners to manage permissions for other users.
    |
    */
    'permissions_panels' => ['admin', 'staff'],

    /*
    |--------------------------------------------------------------------------
    | Resource Actions
    |--------------------------------------------------------------------------
    |
    | Default action list used when a Resource does NOT override
    | getPermissionActions(). Each action is combined with the resource
    | slug to form: {action}_{resource_slug}
    |
    */
    'resource_actions' => [
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
        'approve',
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Prefixes
    |--------------------------------------------------------------------------
    |
    | Prefixes applied to Page and Widget permission names.
    |
    | Page   → {page_prefix}{slug}   (e.g., page_qr_code_manager)
    | Widget → {widget_prefix}{slug} (e.g., widget_gym_revenue_chart)
    |
    */
    'page_prefix' => 'page_',
    'widget_prefix' => 'widget_',

    /*
    |--------------------------------------------------------------------------
    | Guard Name
    |--------------------------------------------------------------------------
    |
    | The Spatie permission guard name used when creating permissions.
    |
    */
    'guard_name' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Full-Access Roles
    |--------------------------------------------------------------------------
    |
    | Roles that automatically receive ALL discovered permissions.
    | These roles bypass any per-role default permission lists.
    |
    */
    'full_access_roles' => ['super_admin', 'gym_owner'],

    /*
    |--------------------------------------------------------------------------
    | Auto-Discover Default Roles
    |--------------------------------------------------------------------------
    |
    | When enabled, the discovery service collects defaultRolePermissions() declarations
    | from Resources, Pages, and Widgets that implement HasFilamentPermissions.
    | These are merged with fallback_default_roles for role-permission assignment.
    |
    */
    'discover_default_roles' => true,

    /*
    |--------------------------------------------------------------------------
    | Fallback Default Roles
    |--------------------------------------------------------------------------
    |
    | Fallback role-to-resource mappings used when discover_default_roles is
    | disabled, or when a resource/page/widget does not declare defaultRolePermissions().
    | Mirrors the structure of config/default-roles-and-permissions.php.
    |
    */
    'fallback_default_roles' => [
        'manager' => [
            'user', 'trainer', 'receptionist', 'gym_member', 'gym',
            'membership_plan', 'attendance', 'payment', 'lead',
        ],
        'receptionist' => [
            'gym_member', 'membership_plan', 'attendance', 'payment', 'lead',
        ],
        'trainer' => [
            'attendance', 'gym_member',
        ],
    ],

];
