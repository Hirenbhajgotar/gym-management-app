<?php

namespace App\Jobs;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\RenewalErrorNotification;
use App\Notifications\RenewalSuccessNotification;
use App\Notifications\TrialEndingNotification;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SubscriptionRenewalJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $backoff = [60, 300, 900]; // Retry after 1min, 5min, 15min

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(SubscriptionService $subscriptionService): void
    {
        Log::info('Starting subscription renewal job');

        $this->processExpiringTrials($subscriptionService);
        $this->processExpiringSubscriptions($subscriptionService);
        $this->processExpiredSubscriptions($subscriptionService);
        $this->processExpiredTrials($subscriptionService);

        Log::info('Subscription renewal job completed');
    }

    /**
     * Process subscriptions with trials ending soon (notify users)
     */
    private function processExpiringTrials(SubscriptionService $subscriptionService): void
    {
        $expiringTrials = Subscription::where('status', SubscriptionStatus::TRIAL)
            ->where('trial_ends_at', '<=', Carbon::now()->addDays(3))
            ->where('trial_ends_at', '>', Carbon::now())
            ->get();

        foreach ($expiringTrials as $subscription) {
            $this->notifyTrialEnding($subscription);
        }

        Log::info("Processed {$expiringTrials->count()} expiring trials");
    }

    /**
     * Process subscriptions expiring soon (attempt renewal)
     */
    private function processExpiringSubscriptions(SubscriptionService $subscriptionService): void
    {
        $expiringSubscriptions = Subscription::active()
            ->where('current_period_end', '<=', Carbon::now()->addDays(3))
            ->where('current_period_end', '>', Carbon::now())
            ->get();

        foreach ($expiringSubscriptions as $subscription) {
            $this->attemptRenewal($subscription, $subscriptionService);
        }

        Log::info("Processed {$expiringSubscriptions->count()} expiring subscriptions");
    }

    /**
     * Process already expired subscriptions
     */
    private function processExpiredSubscriptions(SubscriptionService $subscriptionService): void
    {
        $expiredCount = $subscriptionService->checkExpiredSubscriptions();
        Log::info("Expired {$expiredCount} subscriptions");
    }

    /**
     * Process expired trials
     */
    private function processExpiredTrials(SubscriptionService $subscriptionService): void
    {
        $expiredCount = $subscriptionService->checkExpiredTrials();
        Log::info("Expired {$expiredCount} trial subscriptions");
    }

    /**
     * Attempt to renew a subscription
     */
    private function attemptRenewal(Subscription $subscription, SubscriptionService $subscriptionService): void
    {
        try {
            // Here you would integrate with your payment gateway
            // For now, we'll simulate successful renewal
            $paymentSuccessful = $this->processPayment($subscription);

            if ($paymentSuccessful) {
                $subscriptionService->renewSubscription($subscription);
                $this->notifyRenewalSuccess($subscription);
                Log::info("Successfully renewed subscription for user {$subscription->user_id}");
            } else {
                // Payment failed - could suspend or cancel based on your policy
                $subscriptionService->suspendSubscription($subscription);
                $this->notifyPaymentFailed($subscription);
                Log::warning("Failed to renew subscription for user {$subscription->user_id}");
            }
        } catch (\Exception $e) {
            Log::error("Error renewing subscription {$subscription->id}: {$e->getMessage()}");
            $this->notifyRenewalError($subscription, $e);
        }
    }

    /**
     * Process payment through payment gateway
     * This is a placeholder - integrate with your actual payment provider
     */
    private function processPayment(Subscription $subscription): bool
    {
        // TODO: Integrate with payment gateway (Stripe, Razorpay, etc.)
        // For now, simulate 95% success rate
        return rand(1, 100) <= 95;
    }

    /**
     * Send notification for trial ending
     */
    private function notifyTrialEnding(Subscription $subscription): void
    {
        $subscription->user->notify(new TrialEndingNotification($subscription));
        Log::info("Trial ending notification sent to user {$subscription->user_id}");
    }

    /**
     * Send notification for successful renewal
     */
    private function notifyRenewalSuccess(Subscription $subscription): void
    {
        $subscription->user->notify(new RenewalSuccessNotification($subscription));
        Log::info("Renewal success notification sent to user {$subscription->user_id}");
    }

    /**
     * Send notification for payment failure
     */
    private function notifyPaymentFailed(Subscription $subscription): void
    {
        $subscription->user->notify(new PaymentFailedNotification($subscription));
        Log::warning("Payment failure notification sent to user {$subscription->user_id}");
    }

    /**
     * Send notification for renewal error
     */
    private function notifyRenewalError(Subscription $subscription, \Exception $e): void
    {
        $subscription->user->notify(new RenewalErrorNotification($subscription, $e));
        Log::error("Renewal error notification sent to user {$subscription->user_id}: {$e->getMessage()}");
    }
}
