<?php

namespace App\Listeners;

use Filament\Events\TenantSet as EventsTenantSet;
use Illuminate\Support\Facades\Session;

class UpdateSpatiePermissionCacheOnTenantSwitch
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * When switching tenants, set the new team context.
     * The custom TeamPermissionRegistrar automatically uses per-gym cache keys,
     * so we don't need to clear the global cache anymore.
     */
    public function handle(EventsTenantSet $event): void
    {
        $tenantId = $event->getTenant()?->getKey();

        if ($tenantId) {
            setPermissionsTeamId($tenantId);
            Session::put('permission_team_id', $tenantId);
        }

        // Note: No need to call forgetCachedPermissions() here.
        // The TeamPermissionRegistrar uses per-gym cache keys (e.g., "spatie.permission.cache.gym.123"),
        // so each gym's permissions are cached separately and loaded correctly.
    }
}
