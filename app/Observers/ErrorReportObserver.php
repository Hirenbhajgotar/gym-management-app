<?php

namespace App\Observers;

use App\Helpers\ParserHelper;
use App\Models\ErrorReport;

class ErrorReportObserver
{
    /**
     * Handle the ErrorReport "created" event.
     */
    public function created(ErrorReport $errorReport): void
    {
        $this->clearCache($errorReport);
    }

    /**
     * Handle the ErrorReport "updated" event.
     */
    public function updated(ErrorReport $errorReport): void
    {
        $this->clearCache($errorReport);
    }

    /**
     * Handle the ErrorReport "deleted" event.
     */
    public function deleted(ErrorReport $errorReport): void
    {
        $this->clearCache($errorReport);
    }

    /**
     * Handle the ErrorReport "restored" event.
     */
    public function restored(ErrorReport $errorReport): void
    {
        $this->clearCache($errorReport);
    }

    /**
     * Handle the ErrorReport "force deleted" event.
     */
    public function forceDeleted(ErrorReport $errorReport): void
    {
        $this->clearCache($errorReport);
    }

    private function clearCache(ErrorReport $errorReport): void
    {
        ParserHelper::clearCache(ErrorReport::NAVIGATION_BADGE_CACHE_KEY);
    }
}
