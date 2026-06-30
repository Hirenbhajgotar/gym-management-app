<?php

declare(strict_types=1);

namespace App\Observers;

use App\Helpers\ParserHelper;
use App\Models\BiometricDevice;

class BiometricDeviceObserver
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
     * Clear the navigation badge cache for the device's gym.
     */
    private function clearCache(BiometricDevice $biometricDevice): void
    {
        ParserHelper::clearCache(BiometricDevice::NAVIGATION_BADGE_CACHE_KEY . $biometricDevice->gym_id);
    }
}
