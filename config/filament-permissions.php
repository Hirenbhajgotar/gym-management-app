<?php

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

];
