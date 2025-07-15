<?php

namespace App\Http\Middleware;

use App\Models\Gym;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
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
        $tenant = null;

        // Check if we have a tenant parameter in the route
        if ($request->route() && $request->route()->parameter('tenant')) {
            $tenantModel = app(config('filament.tenant_model', Gym::class));
            $tenant = $tenantModel::where('slug', $request->route()->parameter('tenant'))->first();

            Session::put('permission_team_id', $tenant?->id);
        }

        // Fallback to Filament::getTenant()
        if (!$tenant) {
            $tenant = Filament::getTenant();
        }

        if ($tenant) {
            setPermissionsTeamId($tenant->id);

            // Clear cached permissions for the current user
            $user = auth()->user();

            // Clear cached permissions and reload roles for the current team context
            if ($user) {
                $user->forgetCachedPermissions();
                $user->load('roles');
            }
        }
        return $next($request);
    }
}
