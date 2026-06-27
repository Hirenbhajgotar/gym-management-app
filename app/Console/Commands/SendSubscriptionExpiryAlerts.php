<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Notifications\PlanExpiryNotification;
use App\Notifications\SubscriptionExpired;
use App\Notifications\TrialEndingNotification;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

class SendSubscriptionExpiryAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:send-subscription-expiry-alerts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send expiry alerts for gym subscriptions and trials';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expiring subscriptions and trials...');

        $this->processExpiringTrials();
        $this->processExpiringSubscriptions();
        $this->processExpiredSubscriptions();
        $this->processExpiredTrials();

        $this->info('Subscription expiry alerts sent successfully.');
    }

    /**
     * Process subscriptions with trials ending soon
     */
    private function processExpiringTrials(): void
    {
        $expiringTrials = Subscription::query()->where('status', SubscriptionStatus::TRIAL)
            ->where('trial_ends_at', '<=', Date::now()->addDays(3))
            ->where('trial_ends_at', '>', Date::now())
            ->with('user')
            ->get();

        foreach ($expiringTrials as $subscription) {
            $subscription->user->notify(new TrialEndingNotification($subscription));
        }

        $this->info("Sent trial ending notifications to {$expiringTrials->count()} users");
    }

    /**
     * Process active subscriptions expiring soon
     */
    private function processExpiringSubscriptions(): void
    {
        $expiringSubscriptions = Subscription::active()
            ->where('current_period_end', '<=', Date::now()->addDays(7))
            ->where('current_period_end', '>', Date::now())
            ->with('user')
            ->get();

        foreach ($expiringSubscriptions as $subscription) {
            $subscription->user->notify(new PlanExpiryNotification($subscription));
        }

        $this->info("Sent subscription expiry notifications to {$expiringSubscriptions->count()} users");
    }

    /**
     * Process already expired subscriptions
     */
    private function processExpiredSubscriptions(): void
    {
        $expiredSubscriptions = Subscription::query()->where('status', SubscriptionStatus::ACTIVE)
            ->where('current_period_end', '<=', Date::now())
            ->with('user')
            ->get();

        foreach ($expiredSubscriptions as $subscription) {
            $subscription->user->notify(new SubscriptionExpired($subscription));
            // Update status to expired and set grace period
            $gracePeriodDays = $subscription->user->gym?->settings()->where('key', 'grace_period_days')->first()?->value ?? config('subscription.grace_period_days', 7);
            $subscription->update([
                'status' => SubscriptionStatus::EXPIRED,
                'grace_period_ends_at' => now()->addDays((int) $gracePeriodDays),
            ]);
        }

        $this->info("Sent expired notifications to {$expiredSubscriptions->count()} users");
    }

    /**
     * Process expired trials
     */
    private function processExpiredTrials(): void
    {
        $expiredCount = resolve(SubscriptionService::class)->checkExpiredTrials();
        $this->info("Expired {$expiredCount} trial subscriptions");
    }
}
