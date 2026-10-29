<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

/**
 * Short-lived, non-session storage for a newly generated device API token.
 *
 * Filament serializes notifications into the session. A bearer token placed in
 * a notification body therefore lands in the `sessions` table when the shipped
 * database session driver is used. The token is stored encrypted in the cache
 * and revealed once through a Filament modal instead.
 */
class DeviceTokenReveal
{
    public const TTL_MINUTES = 15;

    private const PREFIX = 'biometric:device:token_reveal';

    public static function cacheKey(int|string $deviceId): string
    {
        return self::PREFIX . ':' . $deviceId;
    }

    public static function put(int|string $deviceId, string $plainToken): bool
    {
        return OneTimeSecretStore::put(self::PREFIX, $deviceId, $plainToken, self::TTL_MINUTES);
    }

    public static function stage(int|string $deviceId, string $plainToken): bool
    {
        return OneTimeSecretStore::stage(self::PREFIX, $deviceId, $plainToken, self::TTL_MINUTES);
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
