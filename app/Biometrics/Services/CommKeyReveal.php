<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

/**
 * Short-lived, non-session storage for a newly generated communication key.
 *
 * The one-time value is encrypted in the configured cache and pulled once by
 * the reveal action. `claimForDisplay()` keeps an encrypted delivery copy for
 * Filament re-renders; `forget()` removes the primary, pending, and delivery
 * copies.
 */
class CommKeyReveal
{
    public const TTL_MINUTES = 15;

    private const PREFIX = 'biometric:device:comm_key_reveal';

    public static function cacheKey(int|string $deviceId): string
    {
        return self::PREFIX . ':' . $deviceId;
    }

    public static function put(int|string $deviceId, string $plainKey): bool
    {
        return OneTimeSecretStore::put(self::PREFIX, $deviceId, $plainKey, self::TTL_MINUTES);
    }

    public static function stage(int|string $deviceId, string $plainKey): bool
    {
        return OneTimeSecretStore::stage(self::PREFIX, $deviceId, $plainKey, self::TTL_MINUTES);
    }

    public static function promote(int|string $deviceId): bool
    {
        return OneTimeSecretStore::promote(self::PREFIX, $deviceId, self::TTL_MINUTES);
    }

    public static function forgetPending(int|string $deviceId): void
    {
        OneTimeSecretStore::forgetPending(self::PREFIX, $deviceId);
    }

    public static function hasPending(int|string $deviceId): bool
    {
        return OneTimeSecretStore::hasPending(self::PREFIX, $deviceId);
    }

    public static function pull(int|string $deviceId): ?string
    {
        return OneTimeSecretStore::pull(self::PREFIX, $deviceId);
    }

    /**
     * @return array{secret: string|null, claimed: bool, first_delivery_read: bool}
     */
    public static function claimForDisplay(int|string $deviceId, ?string $viewerKey = null): array
    {
        return OneTimeSecretStore::claimForDisplay(self::PREFIX, $deviceId, $viewerKey);
    }

    public static function exists(int|string $deviceId, ?string $viewerKey = null): bool
    {
        return OneTimeSecretStore::exists(self::PREFIX, $deviceId)
            || OneTimeSecretStore::deliveryExists(self::PREFIX, $deviceId, $viewerKey);
    }

    public static function forget(int|string $deviceId): void
    {
        OneTimeSecretStore::forget(self::PREFIX, $deviceId);
    }
}
