<?php

namespace App\Observers;

use App\Events\Reports\AttendanceStatsUpdated;
use App\Models\Attendances;
use App\Services\ReportCacheService;

class AttendanceObserver
{
    public function __construct(
        protected ReportCacheService $reportCacheService
    ) {}

    /**
     * Handle the Attendances "created" event.
     */
    public function created(Attendances $attendance): void
    {
        $this->invalidateCachesAndBroadcast($attendance);
    }

    /**
     * Handle the Attendances "updated" event.
     */
    public function updated(Attendances $attendance): void
    {
        $this->invalidateCachesAndBroadcast($attendance);
    }

    /**
     * Handle the Attendances "deleted" event.
     */
    public function deleted(Attendances $attendance): void
    {
        $this->invalidateCachesAndBroadcast($attendance);
    }

    /**
     * Invalidate caches and broadcast event.
     */
    protected function invalidateCachesAndBroadcast(Attendances $attendance): void
    {
        // Invalidate attendance-related caches
        $this->reportCacheService->invalidateAttendanceReports($attendance->gym_id);
        
        // Also invalidate insights as they depend on attendance data
        $this->reportCacheService->invalidateInsights($attendance->gym_id);

        // Broadcast the event
        broadcast(new AttendanceStatsUpdated($attendance->gym_id))->toOthers();
    }
}
