<?php

declare(strict_types=1);

namespace App\Observers;

use App\Helpers\ParserHelper;
use App\Models\Blog;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class BlogObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the Blog "created" event.
     */
    public function created(Blog $blog): void
    {
        $this->clearCache();
    }

    /**
     * Handle the Blog "updated" event.
     */
    public function updated(Blog $blog): void
    {
        $this->clearCache();
    }

    /**
     * Handle the Blog "deleted" event.
     */
    public function deleted(Blog $blog): void
    {
        $this->clearCache();
    }

    /**
     * Handle the Blog "restored" event.
     */
    public function restored(Blog $blog): void
    {
        $this->clearCache();
    }

    /**
     * Handle the Blog "force deleted" event.
     */
    public function forceDeleted(Blog $blog): void
    {
        $this->clearCache();
    }

    private function clearCache(): void
    {
        ParserHelper::clearCache(Blog::NAVIGATION_BADGE_CACHE_KEY);
    }
}
