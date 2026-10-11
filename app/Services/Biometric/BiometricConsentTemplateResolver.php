<?php

declare(strict_types=1);

namespace App\Services\Biometric;

use App\Enums\NotificationType;
use App\Models\WhatsAppTemplate;
use App\Providers\WhatsAppProviderFactory;
use App\Services\WhatsApp\TemplateLookup;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single definition of "which WhatsApp template should carry a biometric
 * consent request for this gym".
 *
 * Two conditions, both required. The row must be one the gym's configured
 * provider can actually dispatch — Twilio needs twilio_template_sid, Meta needs
 * meta_template_id, and a row carrying neither is refused here rather than
 * discovered at the API after a credit has been spent. And it must be a row the
 * provider will find when the job re-resolves it, because the job hands the
 * provider a template *name* and the provider runs its own lookup.
 *
 * That second condition is why allowSystemDefault() below mirrors the providers
 * instead of unconditionally opting in. Resolving a platform-owned (gym_id IS
 * NULL) row in production made this resolver report the channel sendable while
 * Twilio — which only widens to the system default inside its sandbox — and Meta,
 * which never widens at all, returned "not found" for the very row the gate had
 * just approved. The send then failed assertDelivered(), burned all three tries
 * and reached failed() with nothing delivered. The gate's own contract is that it
 * can never report sendable for a template the provider's lookup would miss, so
 * the opt-in follows the provider rather than the other way round.
 *
 * Note that the seeded platform default carries no vendor identifier at all, so
 * it is refused by the identifier predicate regardless of scope: the free-text
 * body in the job is the sandbox path, not a production fallback.
 */
final class BiometricConsentTemplateResolver
{
    public static function resolve(int $gymId): ?WhatsAppTemplate
    {
        return self::query($gymId)->first();
    }

    public static function exists(int $gymId): bool
    {
        return self::query($gymId)->exists();
    }

    /**
     * Why a gym cannot resolve a dispatchable consent template, or null when it
     * can. Kept beside the resolver so the gate never has to re-derive which
     * provider identifier is missing.
     *
     * Total over the same predicate exists() reads, so a caller that has already
     * established !exists() can return this without a fallback of its own: every
     * branch below either returns null — which only happens when exists() would
     * have been true — or a string.
     */
    public static function unavailableReason(int $gymId): ?string
    {
        $identifier = self::identifierColumn($gymId);

        $label = $identifier === 'twilio_template_sid'
            ? 'Twilio content SID'
            : 'Meta template ID';

        $approved = TemplateLookup::applyScope(
            WhatsAppTemplate::query(),
            $gymId,
            allowSystemDefault: self::allowSystemDefault($gymId),
        )->where('name', NotificationType::BIOMETRIC_CONSENT->value);

        if (! (clone $approved)->exists()) {
            return 'No approved, active biometric consent WhatsApp template resolves for this gym.';
        }

        if ($approved->whereNotNull($identifier)->where($identifier, '!=', '')->exists()) {
            return null;
        }

        return "The resolved biometric consent WhatsApp template carries no {$label}, so the provider cannot dispatch it. Attach one in the WhatsApp template settings.";
    }

    /**
     * @return Builder<WhatsAppTemplate>
     */
    private static function query(int $gymId): Builder
    {
        return TemplateLookup::applyScope(
            WhatsAppTemplate::query(),
            $gymId,
            allowSystemDefault: self::allowSystemDefault($gymId),
        )
            ->where('name', NotificationType::BIOMETRIC_CONSENT->value)
            ->where(function (Builder $query) use ($gymId): void {
                $query->whereNotNull(self::identifierColumn($gymId))
                    ->where(self::identifierColumn($gymId), '!=', '');
            });
    }

    /**
     * Whether the platform default is in scope for this gym — deliberately
     * derived from the same two facts the providers derive it from, so this
     * resolver and the provider's own lookup cannot disagree about which rows
     * exist. TwilioMessagingService widens only inside its sandbox; Meta has no
     * sandbox and never widens.
     */
    private static function allowSystemDefault(int $gymId): bool
    {
        return (bool) config('services.twilio.use_sandbox', false)
            && WhatsAppProviderFactory::getProviderForGym($gymId) === WhatsAppProviderFactory::PROVIDER_TWILIO;
    }

    /**
     * The column carrying the vendor identifier for the gym's configured
     * provider. Resolved per gym because the same row can be dispatchable for a
     * Meta gym and undeliverable for a Twilio one.
     */
    private static function identifierColumn(int $gymId): string
    {
        return WhatsAppProviderFactory::getProviderForGym($gymId) === WhatsAppProviderFactory::PROVIDER_TWILIO
            ? 'twilio_template_sid'
            : 'meta_template_id';
    }
}
