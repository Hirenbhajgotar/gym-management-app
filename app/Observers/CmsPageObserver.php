<?php

namespace App\Observers;

use App\Helpers\ParserHelper;
use App\Models\CmsPage;

class CmsPageObserver
{
    /**
     * Handle the CmsPage "created" event.
     */
    public function created(CmsPage $cmsPage): void
    {
        $this->clearCache();
    }

    /**
     * Handle the CmsPage "updated" event.
     */
    public function updated(CmsPage $cmsPage): void
    {
        $this->clearCache();
    }

    /**
     * Handle the CmsPage "deleted" event.
     */
    public function deleted(CmsPage $cmsPage): void
    {
        $this->clearCache();
    }

    /**
     * Handle the CmsPage "restored" event.
     */
    public function restored(CmsPage $cmsPage): void
    {
        $this->clearCache();
    }

    /**
     * Handle the CmsPage "force deleted" event.
     */
    public function forceDeleted(CmsPage $cmsPage): void
    {
        $this->clearCache();
    }

    private function clearCache(): void
    {
        if (auth()->check()) {
            ParserHelper::clearCache('cms_page_navigation_badge_count_' . auth()->id());
        }
    }
}
