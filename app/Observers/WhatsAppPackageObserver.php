<?php

namespace App\Observers;

use App\Helpers\ParserHelper;
use App\Models\WhatsAppPackage;

class WhatsAppPackageObserver
{
    /**
     * Handle the WhatsAppPackage "created" event.
     */
    public function created(WhatsAppPackage $whatsAppPackage): void
    {
        $this->clearCache();
    }

    /**
     * Handle the WhatsAppPackage "updated" event.
     */
    public function updated(WhatsAppPackage $whatsAppPackage): void
    {
        $this->clearCache();
    }

    /**
     * Handle the WhatsAppPackage "deleted" event.
     */
    public function deleted(WhatsAppPackage $whatsAppPackage): void
    {
        $this->clearCache();
    }

    /**
     * Handle the WhatsAppPackage "restored" event.
     */
    public function restored(WhatsAppPackage $whatsAppPackage): void
    {
        $this->clearCache();
    }

    /**
     * Handle the WhatsAppPackage "force deleted" event.
     */
    public function forceDeleted(WhatsAppPackage $whatsAppPackage): void
    {
        $this->clearCache();
    }

    private function clearCache(): void
    {
        ParserHelper::clearCache([
            WhatsAppPackage::NAVIGATION_BADGE_CACHE_KEY,
        ]);
    }
}
