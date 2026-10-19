<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use App\Biometrics\DTOs\GuardResult;
use App\Enums\PaymentStatus;
use App\Models\GymMember;
use Illuminate\Support\Carbon;

class MembershipGuard
{
    public function canCheckIn(GymMember $gymMember): GuardResult
    {
        if (! $gymMember->canAccessGym()) {
            return GuardResult::deny($this->resolveReason($gymMember));
        }

        if (! $gymMember->hasActiveBiometricConsent()) {
            return GuardResult::deny('no_biometric_consent');
        }

        return GuardResult::allow();
    }

    /**
     * The single denial code a blocked member's check-in resolves to.
     *
     * The clauses mirror {@see GymMember::canAccessGym()} one for one and in the
     * same order — archived, absent/lapsed membership, unpaid, frozen — so the
     * code names the clause that actually denied access rather than an older,
     * also-true symptom. `access_denied` is the unreachable-with-a-denial
     * catch-all: every denial path is claimed by a clause above it.
     */
    public function resolveReason(GymMember $gymMember): string
    {
        if ($gymMember->is_archived) {
            return 'member_archived';
        }

        $membershipEnd = $gymMember->membership_end;
        /** @var Carbon|null $membershipEnd */
        if ($membershipEnd === null) {
            return 'no_membership';
        }

        if ($membershipEnd->isPast()) {
            return 'membership_expired';
        }

        if ($gymMember->payment_status === null || ! in_array($gymMember->payment_status, [PaymentStatus::COMPLETED, PaymentStatus::PARTIAL], true)) {
            return 'payment_pending';
        }

        if ($gymMember->isFrozen()) {
            return 'membership_frozen';
        }

        return 'access_denied';
    }

    /**
     * The member-facing sentence for the code returned by {@see self::resolveReason()}.
     *
     * The mapping lives here, once, so a caller that has to explain a denial
     * cannot drift from the predicate that produced it. resolveReason() walks
     * {@see GymMember::canAccessGym()}'s clauses in the same order, so the
     * sentence names the clause that denied access rather than a different,
     * also-true cause.
     */
    public function reasonMessage(GymMember $gymMember): string
    {
        return match ($this->resolveReason($gymMember)) {
            'member_archived' => 'Your membership has been archived.',
            'no_membership' => 'No active membership is on record.',
            'membership_frozen' => 'Your membership is currently frozen.'
                . ($gymMember->frozen_until !== null
                    ? ' It will be unfrozen on ' . $gymMember->frozen_until->format('F j, Y') . '.'
                    : ''),
            'membership_expired' => 'Your membership has expired.',
            'payment_pending' => 'Your payment is not completed.',
            default => 'Access to the gym is currently denied.',
        };
    }
}
