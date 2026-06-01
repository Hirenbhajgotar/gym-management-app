<?php

namespace App\Observers;

use App\Helpers\ParserHelper;
use App\Models\WhatsAppAccount;
use App\Providers\WhatsAppProviderFactory;

class WhatsAppAccountObserver
{
    /**
     * Handle the WhatsAppAccount "created" event.
     */
    public function created(WhatsAppAccount $whatsAppAccount): void
    {
        $this->clearCache($whatsAppAccount);
        $this->clearFactoryCache($whatsAppAccount);
    }

    /**
     * Handle the WhatsAppAccount "updated" event.
     */
    public function updated(WhatsAppAccount $whatsAppAccount): void
    {
        $this->clearCache($whatsAppAccount);
        $this->clearFactoryCache($whatsAppAccount);
    }

    /**
     * Handle the WhatsAppAccount "deleted" event.
     */
    public function deleted(WhatsAppAccount $whatsAppAccount): void
    {
        $this->clearCache($whatsAppAccount);
        $this->clearFactoryCache($whatsAppAccount);
    }

    /**
     * Handle the WhatsAppAccount "restored" event.
     */
    public function restored(WhatsAppAccount $whatsAppAccount): void
    {
        $this->clearCache($whatsAppAccount);
        $this->clearFactoryCache($whatsAppAccount);
    }

    /**
     * Handle the WhatsAppAccount "force deleted" event.
     */
    public function forceDeleted(WhatsAppAccount $whatsAppAccount): void
    {
        $this->clearCache($whatsAppAccount);
        $this->clearFactoryCache($whatsAppAccount);
    }

    private function clearCache(WhatsAppAccount $whatsAppAccount): void
    {
        ParserHelper::clearCache([
            WhatsAppAccount::NAVIGATION_BADGE_CACHE_KEY,
        ]);
    }

    private function clearFactoryCache(WhatsAppAccount $whatsAppAccount): void
    {
        // Clear provider factory cache when account changes
        WhatsAppProviderFactory::invalidateProviderCache($whatsAppAccount->gym_id);
    }
}
