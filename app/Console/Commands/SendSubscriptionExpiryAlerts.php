<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\PlanExpiryNotification;
use App\Notifications\SubscriptionExpired;
use App\Notifications\TrialEndingNotification;
use App\Enums\SubscriptionStatus;
use Illuminate\Console\Command;
use Carbon\Carbon;

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

        $this->info('Subscription expiry alerts sent successfully.');
    }

    /**
     * Process subscriptions with trials ending soon
     */
    private function processExpiringTrials(): void
    {
        $expiringTrials = Subscription::where('status', SubscriptionStatus::TRIAL)
            ->where('trial_ends_at', '<=', Carbon::now()->addDays(3))
            ->where('trial_ends_at', '>', Carbon::now())
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
            ->where('current_period_end', '<=', Carbon::now()->addDays(7))
            ->where('current_period_end', '>', Carbon::now())
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
        $expiredSubscriptions = Subscription::where('status', SubscriptionStatus::ACTIVE)
            ->where('current_period_end', '<=', Carbon::now())
            ->with('user')
            ->get();

        foreach ($expiredSubscriptions as $subscription) {
            $subscription->user->notify(new SubscriptionExpired($subscription));
            // Update status to expired
            $subscription->update(['status' => SubscriptionStatus::EXPIRED]);
        }

        $this->info("Sent expired notifications to {$expiredSubscriptions->count()} users");
    }
}
