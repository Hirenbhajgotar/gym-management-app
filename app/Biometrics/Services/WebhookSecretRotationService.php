<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use App\Models\BiometricDevice;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Per-device atomic rotation for credentials that are delivered through the
 * one-time reveal modal.
 *
 * The contract is:
 *  1. acquire the per-device cache lock (fail closed if it cannot be created
 *     or is held by another rotation);
 *  2. stage the new value in the reveal store;
 *  3. commit the database write;
 *  4. promote the staged value (or forget only the pending copy on failure).
 *
 * The previous secret and its reveal are never destroyed before the database
 * write commits, so a failed database step cannot lock the operator out of the
 * secret the device still expects.
 *
 * @phpstan-type RotationResult array{
 *     rotated: bool,
 *     reveal_available: bool,
 *     secret: string|null,
 *     failure: string|null
 * }
 */
class WebhookSecretRotationService
{
    public const LOCK_TTL_SECONDS = 10;

    public const MANUAL_WEBHOOK_SECRET_MIN_LENGTH = 32;

    public const MANUAL_WEBHOOK_SECRET_PATTERN = '/^[A-Za-z0-9._~+\/=-]+$/';

    public static function webhookSecretLockKey(int|string $deviceId): string
    {
        return "biometric:device:{$deviceId}:webhook-secret-rotation";
    }

    /**
     * The single lock key guarding every `provider_config` read-modify-write
     * for a device: the edit page, webhook-secret rotation, the
     * `rotateCommKey` action, and the provisioning command.
     *
     * Separate writers using different keys could each read the same config,
     * merge a change, and overwrite the other writer's credential.
     */
    public static function providerConfigLockKey(int|string $deviceId): string
    {
        return "biometric:device:{$deviceId}:provider-config";
    }

    public static function tokenLockKey(int|string $deviceId): string
    {
        return "biometric:device:{$deviceId}:token-rotation";
    }

    /**
     * @return RotationResult
     */
    public function rotateWebhookSecret(BiometricDevice $device): array
    {
        return $this->withLock(
            self::webhookSecretLockKey(self::deviceKey($device)),
            function () use ($device): array {
                return $this->withLock(
                    self::providerConfigLockKey(self::deviceKey($device)),
                    function () use ($device): array {
                        try {
                            $device->refresh();
                        } catch (Throwable $exception) {
                            Log::error('ADMS: webhook secret rotation could not reload the device', [
                                'device_id' => self::deviceKey($device),
                                'exception' => $exception::class,
                            ]);

                            return $this->failure('The device could not be reloaded, so the webhook secret was not rotated. Try again.');
                        }

                        if ($device->providerConfigIsUnreadable()) {
                            return $this->failure('The stored provider configuration is unreadable, so the webhook secret was not rotated.');
                        }

                        try {
                            $config = is_array($device->provider_config) ? $device->provider_config : [];
                        } catch (Throwable $exception) {
                            Log::error('ADMS: webhook secret rotation could not read provider_config', [
                                'device_id' => self::deviceKey($device),
                                'exception' => $exception::class,
                            ]);

                            return $this->failure('The stored provider configuration is unreadable, so the webhook secret was not rotated.');
                        }

                        $secret = bin2hex(random_bytes(32));

                        if (! WebhookSecretReveal::stage(self::deviceKey($device), $secret)) {
                            Log::critical('ADMS: webhook secret rotation could not stage the reveal payload', [
                                'device_id' => self::deviceKey($device),
                            ]);

                            return $this->failure('The one-time reveal store is unavailable, so the existing webhook secret is unchanged. Try again once the cache is healthy.');
                        }

                        $config['webhook_secret'] = $secret;

                        try {
                            $device->update(['provider_config' => $config]);
                        } catch (Throwable $exception) {
                            WebhookSecretReveal::forgetPending(self::deviceKey($device));

                            Log::error('ADMS: webhook secret rotation failed after the reveal was staged', [
                                'device_id' => self::deviceKey($device),
                                'exception' => $exception::class,
                            ]);

                            return $this->failure('The database update failed and the existing webhook secret is unchanged.');
                        }

                        if (! WebhookSecretReveal::promote(self::deviceKey($device))) {
                            Log::critical('ADMS: webhook secret committed but could not be promoted for reveal', [
                                'device_id' => self::deviceKey($device),
                            ]);

                            return [
                                'rotated' => true,
                                'reveal_available' => false,
                                'secret' => null,
                                'failure' => 'The database secret was updated, but it could not be stored for one-time reveal. Generate a new secret once the cache is healthy.',
                            ];
                        }

                        return [
                            'rotated' => true,
                            'reveal_available' => true,
                            'secret' => $secret,
                            'failure' => null,
                        ];
                    },
                    'Another change to this device is in progress. The existing webhook secret is unchanged; wait a moment and try again.'
                );
            }
        );
    }

    /**
     * Regenerate the device API token using the same stage/commit/promote
     * contract as the webhook secret.
     *
     * @return RotationResult
     */
    public function regenerateToken(BiometricDevice $device): array
    {
        return $this->withLock(
            self::tokenLockKey(self::deviceKey($device)),
            function () use ($device): array {
                try {
                    $device->refresh();
                } catch (Throwable $exception) {
                    Log::error('ADMS: token regeneration could not reload the device', [
                        'device_id' => self::deviceKey($device),
                        'exception' => $exception::class,
                    ]);

                    return $this->failure('The device could not be reloaded, so the existing API token is unchanged. Try again.');
                }

                $oldHash = is_string($device->token_hash) ? $device->token_hash : null;
                $plainToken = bin2hex(random_bytes(32));
                $newHash = hash('sha256', $plainToken);

                if (! DeviceTokenReveal::stage(self::deviceKey($device), $plainToken)) {
                    Log::critical('ADMS: token regeneration could not stage the reveal payload', [
                        'device_id' => self::deviceKey($device),
                    ]);

                    return $this->failure('The one-time reveal store is unavailable, so the existing API token is unchanged. Try again once the cache is healthy.');
                }

                try {
                    $device->update([
                        'device_token' => $plainToken,
                        'token_hash' => $newHash,
                    ]);
                } catch (Throwable $exception) {
                    DeviceTokenReveal::forgetPending(self::deviceKey($device));

                    Log::error('ADMS: token regeneration failed after the reveal was staged', [
                        'device_id' => self::deviceKey($device),
                        'exception' => $exception::class,
                    ]);

                    return $this->failure('The database update failed and the existing API token is unchanged.');
                }

                try {
                    if ($oldHash !== null && $oldHash !== '') {
                        Cache::forget("biometric:device:token:{$oldHash}");
                    }

                    Cache::forget("biometric:device:token:{$newHash}");
                } catch (Throwable $exception) {
                    // The database token is already committed; cache cleanup is
                    // best-effort and must not turn a successful rotation into
                    // a reported failure.
                    Log::warning('ADMS: token cache invalidation failed after regeneration', [
                        'device_id' => self::deviceKey($device),
                        'exception' => $exception::class,
                    ]);
                }

                if (! DeviceTokenReveal::promote(self::deviceKey($device))) {
                    Log::critical('ADMS: token committed but could not be promoted for reveal', [
                        'device_id' => self::deviceKey($device),
                    ]);

                    return [
                        'rotated' => true,
                        'reveal_available' => false,
                        'secret' => null,
                        'failure' => 'The database token was updated, but it could not be stored for one-time reveal. Regenerate again once the cache is healthy.',
                    ];
                }

                return [
                    'rotated' => true,
                    'reveal_available' => true,
                    'secret' => $plainToken,
                    'failure' => null,
                ];
            }
        );
    }

    /**
     * Forget a previously published reveal and audit a manual webhook-secret
     * write.
     *
     * Kept for the create page, where no rotation can overlap: the new device
     * has no earlier reveal to preserve. The edit page calls
     * `auditManualWebhookSecretWrite()` after commit and forgets the reveal
     * inside the provider-config lock so a concurrent Generate's promoted
     * reveal is never deleted by a late forget.
     */
    public function recordManualWebhookSecretWrite(BiometricDevice $device): void
    {
        WebhookSecretReveal::forget(self::deviceKey($device));

        $this->auditManualWebhookSecretWrite($device);
    }

    /**
     * Audit a manual webhook-secret write without touching the reveal store.
     *
     * The caller owns the in-lock forget; running it again after the
     * transaction commits could delete a concurrent Generate's reveal.
     */
    public function auditManualWebhookSecretWrite(BiometricDevice $device): void
    {
        try {
            activity()
                ->performedOn($device)
                ->withProperties([
                    'gym_id' => $device->gym_id,
                    'action' => 'adms_webhook_secret_set_manually',
                ])
                ->log('ADMS device webhook secret set manually');
        } catch (Throwable $exception) {
            Log::warning('ADMS: manual webhook secret audit failed', [
                'device_id' => self::deviceKey($device),
                'exception' => $exception::class,
            ]);
        }
    }

    /**
     * Normalize an Eloquent key for the typed reveal/lock helpers.
     */
    private static function deviceKey(BiometricDevice $device): int|string
    {
        $key = $device->getKey();

        return is_int($key) || is_string($key) ? $key : 0;
    }

    /**
     * @param  Closure(): RotationResult  $callback
     * @return RotationResult
     */
    private function withLock(
        string $lockKey,
        Closure $callback,
        ?string $contentionMessage = null
    ): array {
        try {
            $lock = Cache::lock($lockKey, self::LOCK_TTL_SECONDS);
        } catch (Throwable $exception) {
            Log::critical('ADMS: could not create a credential rotation lock; failing closed', [
                'lock' => $lockKey,
                'exception' => $exception::class,
            ]);

            return $this->failure('The rotation lock could not be created. Nothing was changed; try again once the cache is healthy.');
        }

        try {
            if (! $lock->get()) {
                return $this->failure(
                    $contentionMessage
                        ?? 'Another credential rotation is already in progress for this device. Nothing was changed; wait a moment and try again.'
                );
            }
        } catch (Throwable $exception) {
            Log::critical('ADMS: could not acquire a credential rotation lock; failing closed', [
                'lock' => $lockKey,
                'exception' => $exception::class,
            ]);

            return $this->failure('The rotation lock could not be acquired. Nothing was changed; try again once the cache is healthy.');
        }

        try {
            return $callback();
        } catch (Throwable $exception) {
            Log::error('ADMS: credential rotation failed inside the device lock', [
                'lock' => $lockKey,
                'exception' => $exception::class,
            ]);

            return $this->failure('The rotation failed before it could complete. The existing credential is unchanged.');
        } finally {
            try {
                $lock->release();
            } catch (Throwable) {
                // The lock TTL is the backstop.
            }
        }
    }

    /**
     * @return RotationResult
     */
    private function failure(string $message): array
    {
        return [
            'rotated' => false,
            'reveal_available' => false,
            'secret' => null,
            'failure' => $message,
        ];
    }
}
