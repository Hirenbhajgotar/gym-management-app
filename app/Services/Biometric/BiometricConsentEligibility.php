<?php

declare(strict_types=1);

namespace App\Services\Biometric;

use App\Models\GymMember;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single definition of "may we ask this member for biometric consent".
 *
 * Two entry points, and they must stay equivalent:
 *   - constrain() for a bulk query, pinning the tenant explicitly;
 *   - isEligible() for one re-hydrated model, read at job time to close the
 *     dispatch/execute race.
 *
 * ADR-002 requires exactly: not archived, membership active, and no active
 * biometric consent. A *frozen* member is deliberately still eligible: the freeze
 * engine extends membership_end only when the operator asks for it, so a freeze
 * that does not extend leaves membership_active() passing on its own. Excluding
 * the frozen population would silently drop them out of a compliance channel. A
 * product decision to change that belongs here, in both methods at once.
 *
 * Delivery reachability is deliberately not part of eligibility. Whether a given
 * member can actually be reached is decided per channel inside the job
 * (hasUsableWhatsapp() for WhatsApp, a present email address for email), because a
 * member reachable by one channel and not the other is still worth asking.
 */
final class BiometricConsentEligibility
{
    /**
     * @param  Builder<GymMember>  $query
     * @return Builder<GymMember>
     */
    public static function constrain(Builder $query, int $gymId): Builder
    {
        return $query
            ->where('gym_id', $gymId)
            ->notArchived()
            ->membershipActive()
            ->withoutActiveBiometricConsent($gymId);
    }

    public static function isEligible(GymMember $member): bool
    {
        return ! $member->is_archived
            && ! $member->hasActiveBiometricConsent()
            && self::hasActiveMembership($member);
    }

    /**
     * Whether a consent link already minted for this member may still be acted on.
     *
     * Narrower than isEligible() in one direction and wider in another, because
     * the two answer different questions. isEligible() asks whether to go and
     * request consent, and a member with no membership_end has no active
     * membership by this application's own definition, so they are never asked.
     * This asks whether a bearer link that already went out is still honoured —
     * and a null membership_end there means the membership was never configured,
     * not that it lapsed, so refusing it would strand a member mid-flow over
     * missing data rather than over an ended membership.
     *
     * Archived and definitively lapsed memberships are refused in both.
     */
    public static function honoursExistingRequest(GymMember $member): bool
    {
        return ! $member->is_archived
            && ($member->membership_end === null || $member->membership_end->gte(today()));
    }

    public static function hasUsableWhatsapp(GymMember $member): bool
    {
        return $member->canReceiveWhatsappNotifications() && filled($member->phone);
    }

    /**
     * Mirrors the membershipActive() scope exactly. membership_end is cast to a
     * date (midnight), so an isFuture() comparison would wrongly exclude a
     * membership that is still valid for the rest of today.
     */
    public static function hasActiveMembership(GymMember $member): bool
    {
        return $member->membership_end !== null
            && $member->membership_end->gte(today());
    }
}
