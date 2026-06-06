<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Marker interface for Filament resources, pages, and widgets that participate
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
 * @see config/filament-permissions.php for panel scanning configuration
 */
interface HasFilamentPermissions {}
