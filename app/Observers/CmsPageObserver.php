<?php

declare(strict_types=1);

namespace App\Observers;

use App\Helpers\ParserHelper;
use App\Models\CmsPage;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class CmsPageObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the CmsPage "created" event.
     */
    public function created(CmsPage $cmsPage): void
    {
        $this->clearCache($cmsPage);
    }

    /**
     * Handle the CmsPage "updated" event.
     */
    public function updated(CmsPage $cmsPage): void
    {
        $this->clearCache($cmsPage);
    }

    /**
     * Handle the CmsPage "deleted" event.
     */
    public function deleted(CmsPage $cmsPage): void
    {
        $this->clearCache($cmsPage);
    }

    /**
     * Handle the CmsPage "restored" event.
     */
    public function restored(CmsPage $cmsPage): void
    {
        $this->clearCache($cmsPage);
    }

    /**
     * Handle the CmsPage "force deleted" event.
     */
    public function forceDeleted(CmsPage $cmsPage): void
    {
        $this->clearCache($cmsPage);
    }

    private function clearCache(CmsPage $cmsPage): void
    {
        ParserHelper::clearCache(CmsPage::NAVIGATION_BADGE_CACHE_KEY);
        ParserHelper::clearCache(CmsPage::CMS_PAGE_CACHE_KEY . $cmsPage->slug);
    }
}
