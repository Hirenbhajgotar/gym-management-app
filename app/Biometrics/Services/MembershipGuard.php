<?php

namespace App\Biometrics\Services;

use App\Enums\PaymentStatus;
use App\Biometrics\DTOs\GuardResult;
use App\Models\GymMember;

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

    private function resolveReason(GymMember $gymMember): string
    {
        if ($gymMember->isFrozen()) {
            return 'membership_frozen';
        }

        if ($gymMember->membership_end?->isPast()) {
            return 'membership_expired';
        }

        if ($gymMember->payment_status !== PaymentStatus::COMPLETED) {
            return 'payment_pending';
        }

        return 'access_denied';
    }
}
