<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionFeature;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /**
     * Create a new subscription for a user
     */
    public function createSubscription(User $user, Plan $plan, array $options = []): Subscription
    {
        // Check if user already has an active subscription
        if ($user->subscription && $user->subscription->isActive()) {
            throw new \Exception('User already has an active subscription');
        }

        $billingCycle = $options['billing_cycle'] ?? 'monthly';
        $trialDays = $options['trial_days'] ?? $plan->trial_days ?? 0;

        $subscription = DB::transaction(function () use ($user, $plan, $billingCycle, $trialDays) {
            // Create subscription
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => $trialDays > 0 ? SubscriptionStatus::TRIAL : SubscriptionStatus::ACTIVE,
                'billing_cycle' => $billingCycle,
                'price' => $billingCycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly,
                'currency' => $plan->currency,
                'trial_ends_at' => $trialDays > 0 ? Carbon::now()->addDays($trialDays) : null,
                'current_period_start' => Carbon::now(),
                'current_period_end' => $this->calculatePeriodEnd(Carbon::now(), $billingCycle),
            ]);

            // Create feature snapshots
            $this->createFeatureSnapshots($subscription, $plan);

            return $subscription;
        });

        return $subscription;
    }

    /**
     * Cancel a subscription
     */
    public function cancelSubscription(Subscription $subscription, bool $immediately = false): bool
    {
        if (! $subscription->isActive()) {
            throw new \Exception('Subscription is not active');
        }

        $updateData = [
            'status' => SubscriptionStatus::CANCELLED,
            'cancelled_at' => Carbon::now(),
        ];

        if ($immediately) {
            $updateData['ends_at'] = Carbon::now();
            $updateData['current_period_end'] = Carbon::now();
        } else {
            $updateData['ends_at'] = $subscription->current_period_end;
        }

        return $subscription->update($updateData);
    }

    /**
     * Renew a subscription
     */
    public function renewSubscription(Subscription $subscription): bool
    {
        if (! $subscription->isActive()) {
            return false;
        }

        $newPeriodStart = $subscription->current_period_end ?? Carbon::now();
        $newPeriodEnd = $this->calculatePeriodEnd($newPeriodStart, $subscription->billing_cycle);

        return $subscription->update([
            'current_period_start' => $newPeriodStart,
            'current_period_end' => $newPeriodEnd,
            'status' => SubscriptionStatus::ACTIVE, // Ensure it's active after renewal
        ]);
    }

    /**
     * Change subscription plan
     */
    public function changePlan(Subscription $subscription, Plan $newPlan, array $options = []): Subscription
    {
        if (! $subscription->isActive()) {
            throw new \Exception('Subscription is not active');
        }

        $billingCycle = $options['billing_cycle'] ?? $subscription->billing_cycle;
        $prorate = $options['prorate'] ?? false;

        DB::transaction(function () use ($subscription, $newPlan, $billingCycle, $prorate) {
            $updateData = [
                'plan_id' => $newPlan->id,
                'billing_cycle' => $billingCycle,
                'price' => $billingCycle === 'yearly' ? $newPlan->price_yearly : $newPlan->price_monthly,
            ];

            // If proration is enabled, adjust the current period
            if ($prorate) {
                $remainingDays = $subscription->daysUntilExpiry();
                if ($remainingDays > 0) {
                    $newPeriodEnd = $this->calculatePeriodEnd(
                        Carbon::now(),
                        $billingCycle,
                        $remainingDays
                    );
                    $updateData['current_period_end'] = $newPeriodEnd;
                }
            }

            $subscription->update($updateData);

            // Delete existing feature snapshots and create new ones
            $subscription->features()->delete();
            $this->createFeatureSnapshots($subscription, $newPlan);
        });

        return $subscription->fresh();
    }

    /**
     * Suspend a subscription
     */
    public function suspendSubscription(Subscription $subscription): bool
    {
        if (! $subscription->isActive()) {
            return false;
        }

        return $subscription->update([
            'status' => SubscriptionStatus::SUSPENDED,
        ]);
    }

    /**
     * Reactivate a suspended subscription
     */
    public function reactivateSubscription(Subscription $subscription): bool
    {
        if ($subscription->status !== SubscriptionStatus::SUSPENDED) {
            return false;
        }

        return $subscription->update([
            'status' => SubscriptionStatus::ACTIVE,
        ]);
    }

    /**
     * Expire a subscription
     */
    public function expireSubscription(Subscription $subscription): bool
    {
        return $subscription->update([
            'status' => SubscriptionStatus::EXPIRED,
        ]);
    }

    /**
     * Check and update expired subscriptions
     */
    public function checkExpiredSubscriptions(): int
    {
        $expiredSubscriptions = Subscription::active()
            ->where('current_period_end', '<', Carbon::now())
            ->get();

        $count = 0;
        foreach ($expiredSubscriptions as $subscription) {
            $this->expireSubscription($subscription);
            $count++;
        }

        return $count;
    }

    /**
     * Check and expire expired trials
     */
    public function checkExpiredTrials(): int
    {
        $expiredTrials = Subscription::where('status', SubscriptionStatus::TRIAL)
            ->where('trial_ends_at', '<', Carbon::now())
            ->get();

        $count = 0;
        foreach ($expiredTrials as $subscription) {
            // Expire trial immediately without grace period
            $subscription->update([
                'status' => SubscriptionStatus::EXPIRED,
                // 'trial_ends_at' => null,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * End trial period
     */
    public function endTrial(Subscription $subscription): bool
    {
        if (! $subscription->isOnTrial()) {
            return false;
        }

        return $subscription->update([
            'status' => SubscriptionStatus::ACTIVE,
            'trial_ends_at' => null,
        ]);
    }

    /**
     * Calculate period end date
     */
    private function calculatePeriodEnd(Carbon $startDate, string $billingCycle, int $additionalDays = 0): Carbon
    {
        $endDate = match ($billingCycle) {
            'yearly' => $startDate->copy()->addYear(),
            'monthly' => $startDate->copy()->addMonth(),
            default => $startDate->copy()->addMonth(),
        };

        if ($additionalDays > 0) {
            $endDate->addDays($additionalDays);
        }

        return $endDate;
    }

    /**
     * Get subscription statistics
     */
    public function getSubscriptionStats(): array
    {
        return [
            'total_active' => Subscription::active()->count(),
            'total_trial' => Subscription::where('status', SubscriptionStatus::TRIAL)->count(),
            'total_cancelled' => Subscription::cancelled()->count(),
            'total_expired' => Subscription::expired()->count(),
            'total_suspended' => Subscription::suspended()->count(),
            'expiring_soon' => Subscription::active()
                ->where('current_period_end', '<=', Carbon::now()->addDays(7))
                ->count(),
        ];
    }

    /**
     * Create feature snapshots for a subscription
     */
    private function createFeatureSnapshots(Subscription $subscription, Plan $plan): void
    {
        $planFeatures = $plan->features;

        foreach ($planFeatures as $planFeature) {
            SubscriptionFeature::create([
                'subscription_id' => $subscription->id,
                'plan_feature_id' => $planFeature->id,
                'feature_name' => $planFeature->feature_name,
                'value' => $planFeature->value,
                'brief_description' => $planFeature->brief_description,
                'order_column' => $planFeature->order_column,
            ]);
        }
    }
}
