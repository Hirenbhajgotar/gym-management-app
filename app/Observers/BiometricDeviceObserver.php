<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\BiometricDevice;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class BiometricDeviceObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the BiometricDevice "created" event.
     */
    public function created(BiometricDevice $biometricDevice): void
    {
        $this->clearCache($biometricDevice);
    }

    /**
     * Handle the BiometricDevice "updated" event.
     */
    public function updated(BiometricDevice $biometricDevice): void
    {
        $this->clearCache($biometricDevice);
    }

    /**
     * Handle the BiometricDevice "deleted" event.
     */
    public function deleted(BiometricDevice $biometricDevice): void
    {
        $this->clearCache($biometricDevice);
    }

    /**
     * Handle the BiometricDevice "restored" event.
     */
    public function restored(BiometricDevice $biometricDevice): void
    {
        $this->clearCache($biometricDevice);
    }

    /**
     * Handle the BiometricDevice "force deleted" event.
     */
    public function forceDeleted(BiometricDevice $biometricDevice): void
    {
        $this->clearCache($biometricDevice);
    }

    /**
     * Clear the navigation badge and device lookup caches.
     *
     * Cache invalidation is best-effort. The observer runs after commit; if a
     * cache backend is down, throwing here would turn a successful create or
     * update into a reported failure (and, in the backfill command, skip
     * printing a key that is already committed).
     */
    private function clearCache(BiometricDevice $biometricDevice): void
    {
        $this->forget(BiometricDevice::NAVIGATION_BADGE_CACHE_KEY . $biometricDevice->gym_id);

        $gymIds = array_filter([
            $biometricDevice->getOriginal('gym_id'),
            $biometricDevice->gym_id,
        ], fn (mixed $id): bool => is_numeric($id));
        foreach ($gymIds as $gymId) {
            $this->forget("biometric:gym:{$gymId}:has_adms_devices");
        }

        $serials = array_filter([
            $biometricDevice->getOriginal('device_serial'),
            $biometricDevice->device_serial,
        ], fn (mixed $serial): bool => is_string($serial) && $serial !== '');
        foreach ($serials as $serial) {
            $this->forget("biometric:device:serial:{$serial}");
        }

        $hashes = array_filter([
            $biometricDevice->getOriginal('token_hash'),
            $biometricDevice->token_hash,
        ], fn (mixed $hash): bool => is_string($hash) && $hash !== '');
        foreach ($hashes as $hash) {
            $this->forget("biometric:device:token:{$hash}");
        }
    }

    private function forget(string $key): void
    {
        try {
            Cache::forget($key);
        } catch (Throwable $exception) {
            Log::warning('Biometric device cache invalidation failed; continuing', [
                'key' => $key,
                'exception' => $exception::class,
            ]);
        }
    }
}
