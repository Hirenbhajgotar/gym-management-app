<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One-time, encrypted cache storage for generated credentials.
 *
 * Filament redirects after create/save, which serializes notifications into the
 * session. Keeping the value here (encrypted at rest) and revealing it through
 * a Livewire modal avoids writing plaintext into the sessions table.
 *
 * Lifecycle:
 *  - `stage()` writes a pending copy without touching the live value.
 *  - the caller commits the database value.
 *  - `promote()` atomically moves the pending copy to the primary slot and
 *    invalidates every delivery copy (via a new delivery generation).
 *
 * `claimForDisplay()` pulls the one-time value and also writes a short-lived
 * encrypted delivery copy scoped to the viewer. Filament re-renders a mounted
 * modal on table search/filter/pagination; without the delivery copy the key
 * would disappear before the operator could copy it. A second viewer never
 * reads another viewer's delivery copy: viewer-aware reads never fall back to
 * the shared legacy key.
 */
class OneTimeSecretStore
{
    public const DELIVERY_TTL_MINUTES = 15;

    /**
     * Write the primary slot and invalidate delivery copies from an earlier
     * reveal.
     *
     * Three forgets and one put are deliberate: forgetting the epoch invalidates
     * every viewer delivery copy without enumerating viewers, forgetting the
     * primary prevents a failed put from leaving a superseded value readable,
     * and forgetting the pending copy prevents a value staged for a superseded
     * rotation from being promoted later.
     */
    public static function put(string $prefix, int|string $id, string $secret, int $ttlMinutes): bool
    {
        $result = self::withLock(
            $prefix,
            $id,
            static function () use ($prefix, $id, $secret, $ttlMinutes): bool {
                try {
                    // A new value supersedes every delivery copy still readable
                    // from an earlier reveal.
                    Cache::forget(self::epochKey($prefix, $id));

                    // Remove the old primary before writing. If the write then
                    // fails, the operator sees "no reveal available" rather
                    // than a superseded key that no longer authenticates.
                    Cache::forget(self::key($prefix, $id));

                    // A staged value belongs to an earlier rotation; promoting
                    // it after this write would resurrect a superseded secret.
                    Cache::forget(self::pendingKey($prefix, $id));

                    return Cache::put(
                        self::key($prefix, $id),
                        Crypt::encryptString($secret),
                        now()->addMinutes($ttlMinutes)
                    );
                } catch (Throwable $exception) {
                    Log::critical('Could not store a one-time secret reveal payload', [
                        'prefix' => $prefix,
                        'id' => $id,
                        'exception' => $exception::class,
                    ]);

                    return false;
                }
            }
        );

        return $result === true;
    }

    /**
     * Stage a value that is not revealed until the database commit succeeds.
     */
    public static function stage(string $prefix, int|string $id, string $secret, int $ttlMinutes): bool
    {
        $result = self::withLock(
            $prefix,
            $id,
            static function () use ($prefix, $id, $secret, $ttlMinutes): bool {
                try {
                    return Cache::put(
                        self::pendingKey($prefix, $id),
                        Crypt::encryptString($secret),
                        now()->addMinutes($ttlMinutes)
                    );
                } catch (Throwable $exception) {
                    Log::critical('Could not stage a one-time secret reveal payload', [
                        'prefix' => $prefix,
                        'id' => $id,
                        'exception' => $exception::class,
                    ]);

                    return false;
                }
            }
        );

        return $result === true;
    }

    /**
     * Promote a staged value to the primary slot.
     *
     * Delivery copies are invalidated by rotating the delivery generation, so
     * an old viewer copy cannot outlive a rotation.
     *
     * Every failure path invalidates the delivery epoch and the old primary
     * before returning false. A missing pending value can mean the caller's
     * database write already committed, so leaving the previous reveal readable
     * would expose a secret the device no longer accepts.
     */
    public static function promote(string $prefix, int|string $id, int $ttlMinutes): bool
    {
        $result = self::withLock(
            $prefix,
            $id,
            static function () use ($prefix, $id, $ttlMinutes): bool {
                try {
                    $pending = Cache::get(self::pendingKey($prefix, $id));

                    if (! is_string($pending)) {
                        self::invalidatePrimary($prefix, $id);

                        return false;
                    }

                    self::invalidatePrimary($prefix, $id);

                    $stored = Cache::put(
                        self::key($prefix, $id),
                        $pending,
                        now()->addMinutes($ttlMinutes)
                    );

                    if (! $stored) {
                        return false;
                    }

                    Cache::forget(self::pendingKey($prefix, $id));

                    return true;
                } catch (Throwable $exception) {
                    self::invalidatePrimary($prefix, $id);

                    Log::critical('Could not promote a staged one-time secret reveal payload', [
                        'prefix' => $prefix,
                        'id' => $id,
                        'exception' => $exception::class,
                    ]);

                    return false;
                }
            }
        );

        if ($result === true) {
            return true;
        }

        if ($result === null) {
            // The lock could not be acquired, so the callback never ran. The
            // database may already hold the superseded secret; do not keep its
            // reveal readable.
            self::invalidatePrimary($prefix, $id);
        }

        return false;
    }

    /**
     * Drop only the staged copy. The live primary and its delivery copies are
     * left untouched, which is the correct outcome when the database write
     * fails after a successful stage.
     */
    public static function forgetPending(string $prefix, int|string $id): void
    {
        try {
            Cache::forget(self::pendingKey($prefix, $id));
        } catch (Throwable $exception) {
            Log::warning('Could not forget a staged one-time secret reveal payload', [
                'prefix' => $prefix,
                'id' => $id,
                'exception' => $exception::class,
            ]);
        }
    }

    public static function hasPending(string $prefix, int|string $id): bool
    {
        try {
            return Cache::has(self::pendingKey($prefix, $id));
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Atomically consume the one-time value.
     */
    public static function pull(string $prefix, int|string $id): ?string
    {
        $read = static function () use ($prefix, $id): ?string {
            try {
                return self::decrypt(Cache::pull(self::key($prefix, $id)));
            } catch (Throwable $exception) {
                Log::critical('Could not read a one-time secret reveal payload', [
                    'prefix' => $prefix,
                    'id' => $id,
                    'exception' => $exception::class,
                ]);

                return null;
            }
        };

        $result = self::withLock($prefix, $id, $read);

        return is_string($result) ? $result : null;
    }

    public static function exists(string $prefix, int|string $id): bool
    {
        try {
            return Cache::has(self::key($prefix, $id));
        } catch (Throwable) {
            return false;
        }
    }

    public static function forget(string $prefix, int|string $id): void
    {
        try {
            Cache::forget(self::key($prefix, $id));
            Cache::forget(self::pendingKey($prefix, $id));

            // Rotating the generation makes every viewer delivery copy
            // unreachable without enumerating user ids.
            Cache::forget(self::epochKey($prefix, $id));
        } catch (Throwable $exception) {
            // Best-effort cleanup; TTL is the backstop. Log without the value.
            Log::warning('Could not forget a one-time secret reveal payload', [
                'prefix' => $prefix,
                'id' => $id,
                'exception' => $exception::class,
            ]);
        }
    }

    /**
     * Consume the one-time value once and keep an encrypted delivery copy for
     * later renders of the same mounted modal.
     *
     * The delivery copy is scoped to `$viewerKey` (normally the authenticated
     * user id). A read with a viewer key never falls back to another viewer's
     * copy. `$viewerKey === null` keeps the legacy shared copy for older
     * callers and tests; new UI code must pass the viewer.
     *
     * `first_delivery_read` is true on the claim and on the first delivery-copy
     * read for that viewer, false on simple Livewire re-renders. The caller
     * audits when either `claimed` or `first_delivery_read` is true.
     *
     * @return array{secret: string|null, claimed: bool, first_delivery_read: bool}
     */
    public static function claimForDisplay(string $prefix, int|string $id, ?string $viewerKey = null): array
    {
        $secret = self::pull($prefix, $id);

        if ($secret !== null) {
            self::putDelivery($prefix, $id, $viewerKey, $secret);

            self::markDeliveryRead($prefix, $id, $viewerKey, true);

            return ['secret' => $secret, 'claimed' => true, 'first_delivery_read' => true];
        }

        $delivery = self::delivery($prefix, $id, $viewerKey);

        if ($delivery === null) {
            return ['secret' => null, 'claimed' => false, 'first_delivery_read' => false];
        }

        return [
            'secret' => $delivery,
            'claimed' => false,
            'first_delivery_read' => self::markDeliveryRead($prefix, $id, $viewerKey, false),
        ];
    }

    public static function deliveryExists(string $prefix, int|string $id, ?string $viewerKey = null): bool
    {
        try {
            $epoch = self::currentEpoch($prefix, $id, false);

            if ($epoch === null) {
                return false;
            }

            return Cache::has(self::deliveryKey($prefix, $id, $viewerKey, $epoch));
        } catch (Throwable) {
            return false;
        }
    }

    private static function putDelivery(
        string $prefix,
        int|string $id,
        ?string $viewerKey,
        string $secret,
        ?string $epoch = null
    ): bool {
        try {
            $epoch ??= self::currentEpoch($prefix, $id, true);

            if ($epoch === null) {
                return false;
            }

            return Cache::put(
                self::deliveryKey($prefix, $id, $viewerKey, $epoch),
                Crypt::encryptString($secret),
                now()->addMinutes(self::DELIVERY_TTL_MINUTES)
            );
        } catch (Throwable $exception) {
            Log::warning('Could not store the delivery copy of a one-time secret; the modal may blank on re-render', [
                'prefix' => $prefix,
                'id' => $id,
                'viewer' => $viewerKey ?? 'shared',
                'exception' => $exception::class,
            ]);

            return false;
        }
    }

    private static function delivery(string $prefix, int|string $id, ?string $viewerKey): ?string
    {
        try {
            $epoch = self::currentEpoch($prefix, $id, false);

            if ($epoch === null) {
                return null;
            }

            return self::decrypt(Cache::get(self::deliveryKey($prefix, $id, $viewerKey, $epoch)));
        } catch (Throwable $exception) {
            Log::critical('Could not read the delivery copy of a one-time secret', [
                'prefix' => $prefix,
                'id' => $id,
                'viewer' => $viewerKey ?? 'shared',
                'exception' => $exception::class,
            ]);

            return null;
        }
    }

    /**
     * Mark the first delivery read for a viewer so a Livewire re-render does
     * not write a duplicate audit entry.
     *
     * A fresh claim always counts as a read. When the marker store fails we
     * audit rather than silently skipping the read.
     */
    private static function markDeliveryRead(
        string $prefix,
        int|string $id,
        ?string $viewerKey,
        bool $freshClaim
    ): bool {
        try {
            $key = self::deliveryReadKey($prefix, $id, $viewerKey);
            // Outlive the delivery copy so a read at the TTL boundary cannot
            // be mistaken for a first read and audited twice.
            $ttl = now()->addMinutes(self::DELIVERY_TTL_MINUTES + 1);

            if ($freshClaim) {
                Cache::put($key, true, $ttl);

                return true;
            }

            return Cache::add($key, true, $ttl);
        } catch (Throwable $exception) {
            Log::warning('Could not record a one-time secret delivery read marker', [
                'prefix' => $prefix,
                'id' => $id,
                'viewer' => $viewerKey ?? 'shared',
                'exception' => $exception::class,
            ]);

            return true;
        }
    }

    /**
     * A generation token shared by every delivery copy of the current secret.
     * Forgetting it invalidates every viewer copy at once.
     */
    private static function currentEpoch(string $prefix, int|string $id, bool $create): ?string
    {
        try {
            $epoch = Cache::get(self::epochKey($prefix, $id));

            if (is_string($epoch) && $epoch !== '') {
                return $epoch;
            }

            if (! $create) {
                return null;
            }

            $candidate = bin2hex(random_bytes(8));
            $ttl = now()->addMinutes(self::DELIVERY_TTL_MINUTES + 5);
            Cache::add(self::epochKey($prefix, $id), $candidate, $ttl);

            $stored = Cache::get(self::epochKey($prefix, $id));

            return is_string($stored) && $stored !== '' ? $stored : $candidate;
        } catch (Throwable $exception) {
            Log::warning('Could not read the delivery generation of a one-time secret', [
                'prefix' => $prefix,
                'id' => $id,
                'exception' => $exception::class,
            ]);

            return null;
        }
    }

    private static function decrypt(mixed $payload): ?string
    {
        if (! is_string($payload)) {
            return null;
        }

        try {
            return Crypt::decryptString($payload);
        } catch (Throwable) {
            return null;
        }
    }

    private static function key(string $prefix, int|string $id): string
    {
        return "{$prefix}:{$id}";
    }

    private static function lockKey(string $prefix, int|string $id): string
    {
        return "{$prefix}:{$id}:lock";
    }

    /**
     * Drop the delivery generation and the primary copy.
     *
     * `promote()` calls this on every failure path so a superseded reveal
     * cannot remain readable after the caller's database write committed.
     */
    private static function invalidatePrimary(string $prefix, int|string $id): void
    {
        try {
            Cache::forget(self::epochKey($prefix, $id));
            Cache::forget(self::key($prefix, $id));
        } catch (Throwable $exception) {
            Log::warning('Could not invalidate a superseded one-time secret reveal', [
                'prefix' => $prefix,
                'id' => $id,
                'exception' => $exception::class,
            ]);
        }
    }

    private static function pendingKey(string $prefix, int|string $id): string
    {
        return "{$prefix}:pending:{$id}";
    }

    private static function epochKey(string $prefix, int|string $id): string
    {
        return "{$prefix}:delivery_epoch:{$id}";
    }

    private static function deliveryKey(
        string $prefix,
        int|string $id,
        ?string $viewerKey,
        string $epoch
    ): string {
        $viewer = $viewerKey === null || $viewerKey === '' ? 'shared' : $viewerKey;

        return "{$prefix}:delivery:{$id}:{$viewer}:{$epoch}";
    }

    private static function deliveryReadKey(string $prefix, int|string $id, ?string $viewerKey): string
    {
        $viewer = $viewerKey === null || $viewerKey === '' ? 'shared' : $viewerKey;

        return "{$prefix}:delivery_read:{$id}:{$viewer}";
    }

    /**
     * Use a cache lock when the store supports one. Stores without lock
     * support fall back to the callback because the deployment contract for
     * production is a lock-capable shared cache.
     *
     * A lock timeout or a lock-backend failure fails closed: mutating callers
     * report failure without touching the store, and readers return null. The
     * delivery copy remains available to `claimForDisplay()`.
     */
    private static function withLock(
        string $prefix,
        int|string $id,
        Closure $callback
    ): mixed {
        try {
            $store = Cache::getStore();
        } catch (Throwable $exception) {
            Log::critical('Could not inspect the one-time secret cache store; failing closed', [
                'prefix' => $prefix,
                'id' => $id,
                'exception' => $exception::class,
            ]);

            return null;
        }

        if (! $store instanceof LockProvider) {
            return $callback();
        }

        try {
            $lock = Cache::lock(self::lockKey($prefix, $id), 5);
        } catch (Throwable $exception) {
            Log::critical('Could not create a one-time secret lock; failing closed', [
                'prefix' => $prefix,
                'id' => $id,
                'exception' => $exception::class,
            ]);

            return null;
        }

        try {
            return $lock->block(2, $callback);
        } catch (LockTimeoutException $exception) {
            Log::warning('Timed out waiting for a one-time secret lock; failing closed', [
                'prefix' => $prefix,
                'id' => $id,
                'exception' => $exception::class,
            ]);

            return null;
        } catch (Throwable $exception) {
            // The callback ran (or the lock backend failed) inside the critical
            // section. Never re-run it outside the lock: that would turn a
            // single one-time read into two.
            Log::critical('Could not complete a locked one-time secret reveal', [
                'prefix' => $prefix,
                'id' => $id,
                'exception' => $exception::class,
            ]);

            return null;
        }
    }
}
