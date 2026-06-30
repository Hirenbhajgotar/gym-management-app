<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\PendingRenewalNotification;
use App\Notifications\RenewalErrorNotification;
use App\Notifications\TrialEndingNotification;
use App\Services\RazorpayService;
use App\Services\SubscriptionService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionRenewalJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $backoff = [60, 300, 900]; // Retry after 1min, 5min, 15min

    protected RazorpayService $razorpayService;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->razorpayService = resolve(RazorpayService::class);
    }

    /**
     * Execute the job.
     */
    public function handle(SubscriptionService $subscriptionService): void
    {
        Log::info('Starting subscription renewal job');

        $this->processExpiringTrials();
        $this->processExpiringSubscriptions();
        $this->processExpiredSubscriptions($subscriptionService);
        $this->processExpiredTrials($subscriptionService);

        Log::info('Subscription renewal job completed');
    }

    /**
     * Process subscriptions with trials ending soon (notify users)
     */
    private function processExpiringTrials(): void
    {
        Subscription::with(['user', 'plan'])
            ->where('status', SubscriptionStatus::TRIAL)
            ->where('trial_ends_at', '<=', Date::now()->addDays(3))
            ->where('trial_ends_at', '>', Date::now())
            ->chunk(100, function ($expiringTrials) {
                foreach ($expiringTrials as $subscription) {
                    $this->notifyTrialEnding($subscription);
                }
            });
        $count = Subscription::query()->where('status', SubscriptionStatus::TRIAL)
            ->where('trial_ends_at', '<=', Date::now()->addDays(3))
            ->where('trial_ends_at', '>', Date::now())
            ->count();
        Log::info("Processed {$count} expiring trials");
    }

    /**
     * Process subscriptions expiring soon (attempt renewal)
     */
    private function processExpiringSubscriptions(): void
    {
        Subscription::with(['user', 'plan'])
            ->active()
            ->where('current_period_end', '<=', Date::now()->addDays(3))
            ->where('current_period_end', '>', Date::now())
            ->chunk(100, function ($expiringSubscriptions) {
                foreach ($expiringSubscriptions as $subscription) {
                    $this->attemptRenewal($subscription);
                }
            });
        $count = Subscription::active()
            ->where('current_period_end', '<=', Date::now()->addDays(3))
            ->where('current_period_end', '>', Date::now())
            ->count();
        Log::info("Processed {$count} expiring subscriptions");
    }

    /**
     * Process already expired subscriptions
     */
    private function processExpiredSubscriptions(SubscriptionService $subscriptionService): void
    {
        $expiredCount = $subscriptionService->checkExpiredSubscriptions();
        Log::info("Expired {$expiredCount} subscriptions");

        // Also check for subscriptions with pending renewals that have expired
        $this->processPendingRenewals($subscriptionService);
    }

    /**
     * Process subscriptions with pending renewal payments that have expired
     */
    private function processPendingRenewals(SubscriptionService $subscriptionService): void
    {
        $processedCount = 0;

        Subscription::with(['user', 'plan'])
            ->where('status', SubscriptionStatus::ACTIVE)
            ->where('current_period_end', '<', Date::now())
            ->where('metadata->pending_renewal_order_id', '!=')
            ->chunk(100, function ($pendingRenewals) use ($subscriptionService, &$processedCount) {
                foreach ($pendingRenewals as $subscription) {
                    // Check if the pending renewal order is older than 7 days
                    $orderCreatedAt = Date::parse($subscription->metadata['pending_renewal_created_at'] ?? null);

                    if ($orderCreatedAt && $orderCreatedAt->addDays(7)->isPast()) {
                        // Suspend subscription after grace period
                        $subscriptionService->suspendSubscription($subscription);
                        $this->notifyPaymentFailed($subscription);
                        Log::warning("Suspended subscription {$subscription->id} due to unpaid renewal after grace period");

                        // Clear pending renewal data
                        $metadata = $subscription->metadata ?? [];
                        unset($metadata['pending_renewal_order_id'], $metadata['pending_renewal_payment_id'], $metadata['pending_renewal_created_at']);
                        $subscription->update(['metadata' => $metadata]);
                    }
                    $processedCount++;
                }
            });

        Log::info("Processed {$processedCount} subscriptions with pending renewals");
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
    private function attemptRenewal(Subscription $subscription): void
    {
        try {
            // For automatic renewals without saved payment methods,
            // we create a renewal order and notify the user to complete payment
            // The subscription remains active until the payment deadline
            $this->processPayment($subscription);

            Log::info("Created renewal order for subscription {$subscription->id}, user notified to complete payment");
        } catch (Exception $e) {
            Log::error("Error creating renewal order for subscription {$subscription->id}: {$e->getMessage()}");
            $this->notifyRenewalError($subscription, $e);
        }
    }

    /**
     * Process payment through Razorpay
     * For automatic renewals, we create an order and notify the user to complete payment
     */
    private function processPayment(Subscription $subscription): void
    {
        try {
            DB::beginTransaction();

            // Create Razorpay order for renewal
            $orderData = $this->razorpayService->createOrder([
                'amount' => $subscription->price,
                'currency' => $subscription->currency,
                'receipt' => 'SUB-AUTO-REN-' . $subscription->id . '-' . time(),
                'user_id' => $subscription->user_id,
                'subscription_id' => $subscription->id,
                'plan_id' => $subscription->plan_id,
                'payment_type' => 'subscription_auto_renewal',
                'description' => "Automatic renewal of {$subscription->plan->name} subscription",
                'notes' => [
                    'subscription_id' => $subscription->id,
                    'plan_name' => $subscription->plan->name,
                    'billing_cycle' => $subscription->billing_cycle,
                    'auto_renewal' => true,
                ],
            ]);

            // For automatic renewals without saved payment methods,
            // we create the order and notify the user to complete payment
            // In a production system, you would charge saved payment methods here

            // Store renewal order ID in subscription metadata for tracking
            $metadata = $subscription->metadata ?? [];
            $metadata['pending_renewal_order_id'] = $orderData['order_id'];
            $metadata['pending_renewal_payment_id'] = $orderData['payment_id'];
            $metadata['pending_renewal_created_at'] = Date::now()->toDateTimeString();
            $subscription->update(['metadata' => $metadata]);

            DB::commit();

            // Send notification to user about pending renewal payment
            $this->notifyPendingRenewal($subscription, $orderData);

            Log::info("Created renewal order for subscription {$subscription->id}: {$orderData['order_id']}");

            // Return false to indicate payment is pending (not completed automatically)
            // The subscription will be suspended, and user can complete payment manually
            // return false;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Failed to create renewal order for subscription {$subscription->id}: {$e->getMessage()}");
            throw $e;
        }
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
    private function notifyRenewalError(Subscription $subscription, Exception $e): void
    {
        $subscription->user->notify(new RenewalErrorNotification($subscription, $e));
        Log::error("Renewal error notification sent to user {$subscription->user_id}: {$e->getMessage()}");
    }

    /**
     * Send notification for pending renewal payment
     */
    private function notifyPendingRenewal(Subscription $subscription, array $orderData): void
    {
        $subscription->user->notify(new PendingRenewalNotification($subscription, $orderData));
        Log::info("Pending renewal notification sent to user {$subscription->user_id} for order {$orderData['order_id']}");
    }
}
