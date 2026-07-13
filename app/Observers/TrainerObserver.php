<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\Reports\TrainerStatsUpdated;
use App\Helpers\ParserHelper;
use App\Models\Trainer;
use App\Services\ReportCacheService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class TrainerObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        protected ReportCacheService $reportCacheService
    ) {}

    /**
     * Handle the Trainer "created" event.
     */
    public function created(Trainer $trainer): void
    {
        $this->clearCache($trainer);
    }

    /**
     * Handle the Trainer "updated" event.
     */
    public function updated(Trainer $trainer): void
    {
        $this->clearCache($trainer);
    }

    /**
     * Handle the Trainer "deleted" event.
     */
    public function deleted(Trainer $trainer): void
    {
        $this->clearCache($trainer);
    }

    /**
     * Handle the Trainer "restored" event.
     */
    public function restored(Trainer $trainer): void
    {
        $this->clearCache($trainer);
    }

    /**
     * Handle the Trainer "force deleted" event.
     */
    public function forceDeleted(Trainer $trainer): void
    {
        $this->clearCache($trainer);
    }

    private function clearCache(Trainer $trainer): void
    {
        ParserHelper::clearCache(Trainer::NAVIGATION_BADGE_CACHE_KEY . $trainer->gym_id);

        // Invalidate trainer report caches
        $this->reportCacheService->invalidateTrainerReports($trainer->gym_id);

        // Broadcast real-time event
        broadcast(new TrainerStatsUpdated($trainer->gym_id));
    }
}
