<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Gym;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class TeamsPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
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
                $tenantModel = resolve(config('filament.tenant_model', Gym::class));
                $tenant = $tenantModel::where('slug', $request->route()->parameter('tenant'))->first();
            }

            $this->setTeamId($tenant?->id);
        }

        return $next($request);
    }

    private function setTeamId($tenantId = null)
    {
        $user = Auth::user();

        if ($tenantId) {
            Session::put('permission_team_id', $tenantId);

            // Set the team context - cache key now includes gym_id automatically
            setPermissionsTeamId($tenantId);

            // Only clear the user's cached permissions (lightweight operation)
            // The registrar's getCacheKey() now includes gym_id, so correct cache is used
            if ($user) {
                $user->forgetCachedPermissions();
                // Reload user's roles to ensure role checks use the updated team context
                $user->load('roles');
            }
        }
    }
}
