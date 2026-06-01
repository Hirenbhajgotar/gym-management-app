<?php

namespace App\Http\Middleware;

use App\Models\Attendances;
use App\Models\BiometricConsent;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\FollowUp;
use App\Models\GymMember;
use App\Models\Lead;
use App\Models\MemberPayment;
use App\Models\MembershipPlan;
use App\Models\Trainer;
use App\Models\WhatsAppTemplate;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
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
        $tenant = Filament::getTenant();

        Trainer::addGlobalScope(
            fn (Builder $query) => $query->whereBelongsTo($tenant),
        );

        GymMember::addGlobalScope(
            fn (Builder $query) => $query->whereBelongsTo($tenant),
        );

        MembershipPlan::addGlobalScope(
            fn (Builder $query) => $query->whereBelongsTo($tenant),
        );

        Attendances::addGlobalScope(
            fn (Builder $query) => $query->whereBelongsTo($tenant),
        );

        MemberPayment::addGlobalScope(
            fn (Builder $query) => $query->whereBelongsTo($tenant),
        );

        Lead::addGlobalScope(
            fn (Builder $query) => $query->whereBelongsTo($tenant),
        );

        FollowUp::addGlobalScope(
            fn (Builder $query) => $query->whereBelongsTo($tenant),
        );

        BiometricDevice::addGlobalScope(
            fn (Builder $query) => $query->whereBelongsTo($tenant),
        );

        BiometricConsent::addGlobalScope(
            fn (Builder $query) => $query->whereBelongsTo($tenant),
        );

        BiometricTemplate::addGlobalScope(
            fn (Builder $query) => $query->whereBelongsTo($tenant),
        );

        if (! config('services.twilio.use_sandbox')) {
            WhatsAppTemplate::addGlobalScope(
                fn (Builder $query) => $query->whereBelongsTo($tenant),
            );
        }

        return $next($request);
    }
}
