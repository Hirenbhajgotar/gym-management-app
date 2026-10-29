<?php

declare(strict_types=1);

namespace App\Biometrics\ValueObjects;

use App\Models\BiometricDevice;

/**
 * Outcome of one ADMS authentication attempt.
 *
 * `authenticated` means the request may proceed. `keyVerified` means the
 * presented communication key was actually checked and matched; it is false for
 * unkeyed devices when key enforcement is disabled, which is why inactive-device
 * auto-recovery requires the stronger flag.
 */
final readonly class AdmsAuthenticationResult
{
    private function __construct(
        public ?BiometricDevice $device,
        public bool $authenticated,
        public bool $keyVerified,
        public ?AdmsAuthenticationFailureReason $failureReason = null,
    ) {}

    public static function failure(AdmsAuthenticationFailureReason $reason, ?BiometricDevice $device = null): self
    {
        return new self($device, false, false, $reason);
    }

    public static function success(BiometricDevice $device, bool $keyVerified): self
    {
        return new self($device, true, $keyVerified);
    }
}
