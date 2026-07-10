<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\Reports\RevenueStatsUpdated;
use App\Models\MemberPayment;
use App\Services\DashboardCacheService;
use App\Services\ReportCacheService;
use Exception;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class MemberPaymentObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        protected ReportCacheService $reportCacheService,
        protected DashboardCacheService $dashboardCacheService
    ) {}

    public static function clearPaymentCaches(int $gymId): void
    {
        try {
            $service = resolve(ReportCacheService::class);
            $dashboardService = resolve(DashboardCacheService::class);

            $service->invalidateRevenueReports($gymId);
            $service->invalidateInsights($gymId);
            $dashboardService->invalidateRevenueWidgets($gymId);

            broadcast(new RevenueStatsUpdated($gymId));
        } catch (Exception $e) {
            logger()->error('Failed to clear payment caches: ' . $e->getMessage());
        }
    }

    /**
     * Handle the MemberPayment "created" event.
     */
    public function created(MemberPayment $payment): void
    {
        $this->clearCacheAndBroadcast($payment);
    }

    /**
     * Handle the MemberPayment "updated" event.
     */
    public function updated(MemberPayment $payment): void
    {
        $this->clearCacheAndBroadcast($payment);
    }

    /**
     * Handle the MemberPayment "deleted" event.
     */
    public function deleted(MemberPayment $payment): void
    {
        $this->clearCacheAndBroadcast($payment);
    }

    /**
     * Handle the MemberPayment "restored" event.
     */
    public function restored(MemberPayment $payment): void
    {
        $this->clearCacheAndBroadcast($payment);
    }

    /**
     * Handle the MemberPayment "force deleted" event.
     */
    public function forceDeleted(MemberPayment $payment): void
    {
        $this->clearCacheAndBroadcast($payment);
    }

    /**
     * Clear revenue-related caches and broadcast real-time update event.
     */
    private function clearCacheAndBroadcast(MemberPayment $payment): void
    {
        $gymId = $payment->gym_id;

        if ($gymId) {
            // Invalidate report caches
            $this->reportCacheService->invalidateRevenueReports($gymId);
            $this->reportCacheService->invalidateInsights($gymId);

            // Invalidate dashboard widget caches
            $this->dashboardCacheService->invalidateRevenueWidgets($gymId);

            // Broadcast real-time event
            broadcast(new RevenueStatsUpdated($gymId));
        }
    }
}
