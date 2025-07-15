<?php

namespace App\Listeners;

use Filament\Events\TenantSet as EventsTenantSet;
use Filament\Facades\Filament;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Session;
use Spatie\Permission\PermissionRegistrar;

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
     */
    public function handle(EventsTenantSet $event): void
    {
        $tenantId = $event->getTenant()->getKey();

        $permissionRegistrar = app(PermissionRegistrar::class);

        if ($tenantId) {
            setPermissionsTeamId($tenantId);

            Session::put('permission_team_id', $tenantId);
        }

        // Clear the permission cache to apply the new tenant's permissions immediately.
        $permissionRegistrar->forgetCachedPermissions();
    }
}
