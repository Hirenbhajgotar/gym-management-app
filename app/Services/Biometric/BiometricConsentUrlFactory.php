<?php

declare(strict_types=1);

namespace App\Services\Biometric;

use App\Models\GymMember;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Single minting point for the signed biometric consent URL.
 *
 * The signed URL is an unauthenticated bearer credential: whoever holds it can
 * grant biometric consent for the member until it expires. Both the TTL and the
 * user-facing "valid for N days" copy therefore have to move together, so they
 * live here rather than being repeated at each call site.
 *
 * The clamp is deliberately here and not only in config. config/biometrics.php
 * already bounds the value, but that file is the first thing anyone edits when
 * changing the TTL, and a security ceiling that lives one file away from the
 * value it bounds is not a ceiling. A cached config, a test that overrides it, or
 * a future refactor that reads the raw env value all bypass it silently.
 */
final class BiometricConsentUrlFactory
{
    private const MIN_EXPIRY_DAYS = 1;

    private const MAX_EXPIRY_DAYS = 30;

    public static function expiryDays(): int
    {
        return min(
            self::MAX_EXPIRY_DAYS,
            max(self::MIN_EXPIRY_DAYS, config()->integer('biometrics.consent_url_expiry_days', self::MIN_EXPIRY_DAYS)),
        );
    }

    public static function expiresAt(): Carbon
    {
        return now()->addDays(self::expiryDays());
    }

    /**
     * Human-readable validity window, derived from the TTL so the copy can never
     * drift away from the actual expiry.
     */
    public static function validityLabel(): string
    {
        $days = self::expiryDays();

        return $days === 1 ? '1 day' : "{$days} days";
    }

    /**
     * Mint the signed consent URL for a member.
     *
     * The tenant is always read from the member record rather than from a
     * caller-supplied value, so the URL can never be pointed at another gym.
     */
    public static function make(GymMember $member): string
    {
        return URL::temporarySignedRoute(
            'biometric.consent.show',
            self::expiresAt(),
            [
                'gym_member_id' => $member->id,
                'gym_id' => $member->gym_id,
            ]
        );
    }
}
