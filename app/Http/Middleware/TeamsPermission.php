<?php

namespace App\Http\Middleware;

use App\Models\Gym;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class TeamsPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Filament::getTenant() || Session::has('permission_team_id')) {
            $tenantId = Filament::getTenant()?->id ?? Session::get('permission_team_id');

            $this->setTeamId($tenantId);
        } else {
            $tenant = null;

            // Check if we have a tenant parameter in the route
            if ($request->route() && $request->route()->parameter('tenant')) {
                $tenantModel = app(config('filament.tenant_model', Gym::class));
                $tenant = $tenantModel::where('slug', $request->route()->parameter('tenant'))->first();
            }

            // Fallback to Filament::getTenant()
            if (!$tenant) {
                $tenant = Filament::getTenant();
            }

            $this->setTeamId($tenant?->id);
        }

        return $next($request);
    }

    private function setTeamId($tenantId = null)
    {
        $user = auth()->user();

        if ($tenantId) {
            $permissionRegistrar = app(PermissionRegistrar::class);

            Session::put('permission_team_id', $tenantId);

            setPermissionsTeamId($tenantId);

            $permissionRegistrar->forgetCachedPermissions();

            // Clear cached permissions for the current user
            if ($user) {
                $user->forgetCachedPermissions();
                // Reload user's roles to ensure role checks use the updated team context
                $user->load('roles');
            }
        }
    }
}
