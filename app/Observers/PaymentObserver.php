<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\Reports\RevenueStatsUpdated;
use App\Helpers\ParserHelper;
use App\Models\Payment;
use App\Services\ReportCacheService;

class PaymentObserver
{
    public function __construct(
        protected ReportCacheService $reportCacheService
    ) {}

    /**
     * Handle the Payment "created" event.
     */
    public function created(Payment $payment): void
    {
        $this->clearCache($payment);
    }

    /**
     * Handle the Payment "updated" event.
     */
    public function updated(Payment $payment): void
    {
        $this->clearCache($payment);
    }

    /**
     * Handle the Payment "deleted" event.
     */
    public function deleted(Payment $payment): void
    {
        $this->clearCache($payment);
    }

    /**
     * Handle the Payment "restored" event.
     */
    public function restored(Payment $payment): void
    {
        $this->clearCache($payment);
    }

    /**
     * Handle the Payment "force deleted" event.
     */
    public function forceDeleted(Payment $payment): void
    {
        $this->clearCache($payment);
    }

    private function clearCache(Payment $payment): void
    {
        ParserHelper::clearCache(Payment::PROFILE_LATEST_PAYMENTS_OF_AUTH_USER_CACHE_KEY . $payment->user_id);

        // Get gym_id from payment (assuming Payment has gym_id)
        $gymId = $payment->gym_id ?? $payment->member?->gym_id;

        if ($gymId) {
            // Invalidate report caches
            $this->reportCacheService->invalidateRevenueReports($gymId);
            $this->reportCacheService->invalidateInsights($gymId);

            // Broadcast real-time event
            broadcast(new RevenueStatsUpdated($gymId));
        }
    }
}
