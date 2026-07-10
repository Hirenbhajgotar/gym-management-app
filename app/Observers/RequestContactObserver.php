<?php

declare(strict_types=1);

namespace App\Observers;

use App\Helpers\ParserHelper;
use App\Models\RequestContact;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class RequestContactObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the RequestContact "created" event.
     */
    public function created(RequestContact $requestContact): void
    {
        $this->clearCache();
    }

    /**
     * Handle the RequestContact "updated" event.
     */
    public function updated(RequestContact $requestContact): void
    {
        $this->clearCache();
    }

    /**
     * Handle the RequestContact "deleted" event.
     */
    public function deleted(RequestContact $requestContact): void
    {
        $this->clearCache();
    }

    /**
     * Handle the RequestContact "restored" event.
     */
    public function restored(RequestContact $requestContact): void
    {
        $this->clearCache();
    }

    /**
     * Handle the RequestContact "force deleted" event.
     */
    public function forceDeleted(RequestContact $requestContact): void
    {
        $this->clearCache();
    }

    private function clearCache(): void
    {
        ParserHelper::clearCache(RequestContact::NAVIGATION_BADGE_CACHE_KEY);
    }
}
