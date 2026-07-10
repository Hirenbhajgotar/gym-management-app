<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\WhatsAppTemplate;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Cache;

class WhatsAppTemplateObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the WhatsAppTemplate "created" event.
     */
    public function created(WhatsAppTemplate $whatsAppTemplate): void
    {
        $this->clearCache($whatsAppTemplate);
    }

    /**
     * Handle the WhatsAppTemplate "updated" event.
     */
    public function updated(WhatsAppTemplate $whatsAppTemplate): void
    {
        $this->clearCache($whatsAppTemplate);
    }

    /**
     * Handle the WhatsAppTemplate "deleted" event.
     */
    public function deleted(WhatsAppTemplate $whatsAppTemplate): void
    {
        $this->clearCache($whatsAppTemplate);
    }

    /**
     * Handle the WhatsAppTemplate "restored" event.
     */
    public function restored(WhatsAppTemplate $whatsAppTemplate): void
    {
        $this->clearCache($whatsAppTemplate);
    }

    /**
     * Handle the WhatsAppTemplate "force deleted" event.
     */
    public function forceDeleted(WhatsAppTemplate $whatsAppTemplate): void
    {
        $this->clearCache($whatsAppTemplate);
    }

    private function clearCache(WhatsAppTemplate $whatsAppTemplate): void
    {
        Cache::forget(WhatsAppTemplate::NAVIGATION_BADGE_CACHE_KEY . $whatsAppTemplate->gym_id);
        Cache::forget(WhatsAppTemplate::SUPER_ADMIN_NAVIGATION_BADGE_CACHE_KEY);
    }
}
