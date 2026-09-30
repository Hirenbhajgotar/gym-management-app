<?php

declare(strict_types=1);

namespace App\Observers;

use App\Helpers\ParserHelper;
use App\Models\BiometricDevice;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Cache;

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
     */
    private function clearCache(BiometricDevice $biometricDevice): void
    {
        ParserHelper::clearCache(BiometricDevice::NAVIGATION_BADGE_CACHE_KEY . $biometricDevice->gym_id);

        $serials = array_filter([
            $biometricDevice->getOriginal('device_serial'),
            $biometricDevice->device_serial,
        ], fn (mixed $serial): bool => is_string($serial) && $serial !== '');
        foreach ($serials as $serial) {
            Cache::forget("biometric:device:serial:{$serial}");
        }

        $hashes = array_filter([
            $biometricDevice->getOriginal('token_hash'),
            $biometricDevice->token_hash,
        ], fn (mixed $hash): bool => is_string($hash) && $hash !== '');
        foreach ($hashes as $hash) {
            Cache::forget("biometric:device:token:{$hash}");
        }
    }
}
