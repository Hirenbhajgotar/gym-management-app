<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

/**
 * Short-lived, non-session storage for a newly generated webhook HMAC secret.
 *
 * The generate action used to deliver the secret only through the clipboard,
 * which is undefined on the HTTP/LAN deployments these terminals use. The
 * secret is now stored encrypted in the cache and revealed through the same
 * fallback-capable modal as the comm key and device token.
 *
 * Rotation stages the new secret with `stage()` and only promotes it after the
 * database update commits. A failed write forgets only the pending copy, so the
 * previously valid secret and its reveal remain usable.
 */
class WebhookSecretReveal
{
    public const TTL_MINUTES = 15;

    private const PREFIX = 'biometric:device:webhook_secret_reveal';

    public static function cacheKey(int|string $deviceId): string
    {
        return self::PREFIX . ':' . $deviceId;
    }

    public static function put(int|string $deviceId, string $plainSecret): bool
    {
        return OneTimeSecretStore::put(self::PREFIX, $deviceId, $plainSecret, self::TTL_MINUTES);
    }

    public static function stage(int|string $deviceId, string $plainSecret): bool
    {
        return OneTimeSecretStore::stage(self::PREFIX, $deviceId, $plainSecret, self::TTL_MINUTES);
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
