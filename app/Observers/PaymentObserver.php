<?php

namespace App\Observers;

use App\Helpers\ParserHelper;
use App\Models\Payment;

class PaymentObserver
{
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
    }
}
