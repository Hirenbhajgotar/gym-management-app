<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use App\Biometrics\DTOs\GuardResult;
use App\Enums\PaymentStatus;
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

        if (! in_array($gymMember->payment_status, [PaymentStatus::COMPLETED, PaymentStatus::PARTIAL], true)) {
            return 'payment_pending';
        }

        return 'access_denied';
    }
}
