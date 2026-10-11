<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Enums\WhatsAppTemplateStatusEnum;
use App\Models\WhatsAppTemplate;
use Illuminate\Database\Eloquent\Builder;

/**
 * The one definition of "which WhatsApp template row may this gym send", shared
 * by both messaging providers and by the biometric consent resolver.
 *
 * The predicates live here rather than being restated per caller so that
 * "approved and active, not retired" cannot mean one thing in Twilio and
 * another in Meta — the divergence that let a broken template identifier ship
 * while every test stayed green.
 *
 * The gym_id predicate is grouped inside a closure, so it is a tenant predicate
 * and never an OR that leaks another gym's row.
 *
 * Global scopes are dropped deliberately. The tenant scope registered per
 * request by ApplyTenantScopes is an anonymous closure with no stable
 * identifier; leaving it in place would reduce the system-default branch to
 * dead SQL inside an HTTP request while leaving it live in a queue worker — the
 * same lookup returning different rows depending on where it ran. SoftDeletes is
 * dropped with it, so deleted_at is filtered explicitly: a retired template
 * still carries its live vendor identifiers, and resolving one would bill the
 * gym against a template the provider has withdrawn.
 */
final class TemplateLookup
{
    /**
     * @param  Builder<WhatsAppTemplate>  $query
     * @return Builder<WhatsAppTemplate>
     */
    public static function applyScope(Builder $query, ?int $gymId, bool $allowSystemDefault = false): Builder
    {
        return $query
            ->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where(function (Builder $query) use ($gymId, $allowSystemDefault): void {
                // A null gym id is the system-tenant case (TwilioContext permits
                // it) and can only ever mean the shared system defaults.
                if ($gymId === null) {
                    $query->whereNull('gym_id');

                    return;
                }

                if ($allowSystemDefault) {
                    $query->where('gym_id', $gymId)
                        ->orWhereNull('gym_id');

                    return;
                }

                $query->where('gym_id', $gymId);
            })
            // is_active = true OR NULL: the column defaults to true, so a NULL
            // means "never explicitly configured" rather than "switched off", and
            // only false is a deliberate deactivation.
            ->where('status', WhatsAppTemplateStatusEnum::APPROVED->value)
            ->where(function (Builder $query): void {
                $query->where('is_active', true)
                    ->orWhereNull('is_active');
            })
            ->orderByRaw('gym_id IS NULL ASC');
    }
}
