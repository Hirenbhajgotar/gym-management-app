<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

use App\Models\GymMember;
use App\Services\Biometric\BiometricConsentUrlFactory;

/**
 * Single rule and copy for the "your gym runs biometric check-in, please
 * consent" notice.
 *
 * Previously duplicated byte-for-byte across WelcomeNotification and a
 * WelcomeMemberMail that nothing ever sent, which is how the two drifted from
 * each other's intent.
 */
trait InteractsWithBiometricConsent
{
    public function shouldIncludeBiometricNotice(): bool
    {
        $member = $this->biometricNoticeMember();

        if (! $member instanceof GymMember) {
            return false;
        }

        return $member->gym?->hasActiveBiometricDevice() === true
            && ! $member->hasActiveBiometricConsent();
    }

    public function biometricConsentUrl(): ?string
    {
        if (! $this->shouldIncludeBiometricNotice()) {
            return null;
        }

        $member = $this->biometricNoticeMember();

        return $member instanceof GymMember ? BiometricConsentUrlFactory::make($member) : null;
    }

    public function biometricConsentValidityLabel(): string
    {
        return BiometricConsentUrlFactory::validityLabel();
    }

    abstract protected function biometricNoticeMember(): ?GymMember;
}
