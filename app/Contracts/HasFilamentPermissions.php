<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Interface for Filament resources, pages, and widgets that participate
 * in automatic permission discovery and generation.
 *
 * Classes implementing this interface are discovered by
 * {@see \App\Services\RolesAndPermissionsService::discoverFilamentPermissions()}
 * to auto-generate Spatie Permission entries without hardcoded lists.
 *
 * By default, permissions are derived from the class name:
 * - Resources → `{action}_{slug}` (e.g., `view_any_gym_member`)
 * - Pages     → `page_{slug}`     (e.g., `page_qr_code_manager`)
 * - Widgets   → `widget_{slug}`   (e.g., `widget_gym_revenue_chart`)
 *
 * Implement {@see defaultRolePermissions()} to declare which roles should receive
 * which permission actions by default. The discovery service collects these
 * declarations and merges them with the fallback config.
 *
 * @see config/filament-permissions.php for panel scanning configuration
 */
interface HasFilamentPermissions
{
    /**
     * Get the default role-to-permission-actions mapping for this class.
     *
     * Return an empty array to opt out of automatic role assignment,
     * or map DefaultRole values to arrays of action names (e.g., 'view', 'create', 'update').
     *
     * @return array<string, string[]> role value => permission action names
     */
    public function defaultRolePermissions(): array;
}
