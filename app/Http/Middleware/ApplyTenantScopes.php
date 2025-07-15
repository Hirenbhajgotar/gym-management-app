<?php

namespace App\Http\Middleware;

use App\Models\Attendances;
use App\Models\GymMember;
use App\Models\MembershipPlan;
use App\Models\Trainer;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class ApplyTenantScopes
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = Filament::getTenant()?->id ?? Session::get('permission_team_id');
        if ($tenantId) {
            setPermissionsTeamId($tenantId);

            $user = auth()->user();

            // Clear cached permissions and reload roles for the current team context
            if ($user) {
                $user->forgetCachedPermissions();
                $user->load('roles');
            }
        }

        Trainer::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo(Filament::getTenant()),
        );

        GymMember::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo(Filament::getTenant()),
        );

        MembershipPlan::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo(Filament::getTenant()),
        );

        Attendances::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo(Filament::getTenant()),
        );

        return $next($request);
    }
}
