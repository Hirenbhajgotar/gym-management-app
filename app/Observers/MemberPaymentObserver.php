<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\Reports\RevenueStatsUpdated;
use App\Models\MemberPayment;
use App\Services\DashboardCacheService;
use App\Services\ReportCacheService;

class MemberPaymentObserver
{
    public function __construct(
        protected ReportCacheService $reportCacheService,
        protected DashboardCacheService $dashboardCacheService
    ) {}

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
