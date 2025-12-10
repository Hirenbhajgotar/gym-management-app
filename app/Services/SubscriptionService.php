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
    public function createSubscription(
        User $user,
        Plan $plan,
        array $options = [],
    ): Subscription {
        // Check if user already has an active subscription
        if ($user->subscription()?->isActive()) {
            throw new \Exception('User already has an active subscription');
        }

        // Check if user can get a free trial
        $hasUsedTrial = $user->hasUsedTrial();

        $billingCycle = $options['billing_cycle'] ?? 'monthly';

        $trialDays = 0;
        if (isset($options['trial_days'])) {
            $trialDays = $options['trial_days'];
        } elseif (! $hasUsedTrial && $plan->trial_days) {
            $trialDays = $plan->trial_days;
        }

        if (isset($options['force_status']) && $options['force_status'] !== SubscriptionStatus::TRIAL) {
            $trialDays = 0;
        }

        $subscription = DB::transaction(function () use (
            $user,
            $plan,
            $billingCycle,
            $trialDays,
            $options,
        ) {
            // Determine status - allow override for admin assignments
            $status = $options['force_status'] ?? (
                $trialDays > 0
                ? SubscriptionStatus::TRIAL
                : SubscriptionStatus::ACTIVE
            );

            // Create subscription
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => $status,
                'billing_cycle' => $billingCycle,
                'price' => $billingCycle === 'yearly'
                    ? $plan->price_yearly
                    : $plan->price_monthly,
                'currency' => $plan->currency,
                'trial_ends_at' => $trialDays > 0 ? Carbon::now()->addDays($trialDays) : null,
                'current_period_start' => Carbon::now(),
                'current_period_end' => $this->calculatePeriodEnd(
                    Carbon::now(),
                    $billingCycle,
                ),
            ]);

            // Create feature snapshots
            $this->createFeatureSnapshots($subscription, $plan);

            return $subscription;
        });

        return $subscription;
    }

    /**
     * Create a pending subscription for failed/incomplete payments
     */
    public function createPendingSubscription(
        User $user,
        Plan $plan,
        array $options = [],
    ): Subscription {
        $billingCycle = $options['billing_cycle'] ?? 'monthly';

        $subscription = DB::transaction(function () use (
            $user,
            $plan,
            $billingCycle,
        ) {
            // Create subscription with PENDING status
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::PENDING,
                'billing_cycle' => $billingCycle,
                'price' => $billingCycle === 'yearly'
                    ? $plan->price_yearly
                    : $plan->price_monthly,
                'currency' => $plan->currency,
                'trial_ends_at' => null, // No trial for pending subscriptions
                'current_period_start' => Carbon::now(),
                'current_period_end' => $this->calculatePeriodEnd(
                    Carbon::now(),
                    $billingCycle,
                ),
            ]);

            // Create feature snapshots
            $this->createFeatureSnapshots($subscription, $plan);

            return $subscription;
        });

        return $subscription;
    }

    /**
     * Activate a pending subscription after successful payment
     */
    public function activateSubscription(Subscription $subscription): bool
    {
        if ($subscription->status !== SubscriptionStatus::PENDING) {
            throw new \Exception('Only pending subscriptions can be activated');
        }

        return $subscription->update([
            'status' => SubscriptionStatus::ACTIVE,
            'current_period_start' => Carbon::now(),
            'current_period_end' => $this->calculatePeriodEnd(
                Carbon::now(),
                $subscription->billing_cycle,
            ),
        ]);
    }

    /**
     * Cancel a subscription
     */
    public function cancelSubscription(
        Subscription $subscription,
        bool $immediately = false,
    ): bool {
        if (! $subscription->isActive()) {
            throw new \Exception('Subscription is not active');
        }

        $trialEnd = $subscription->trial_ends_at?->isFuture()
            ? now()
            : $subscription->trial_ends_at;

        $updateData = [
            'status' => SubscriptionStatus::CANCELLED,
            'cancelled_at' => Carbon::now(),
            'trial_ends_at' => $trialEnd ?? null,
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
        // if (! $subscription->isActive()) {
        //     return false;
        // }

        $newPeriodStart = $subscription->current_period_end ?? Carbon::now();
        $newPeriodEnd = $this->calculatePeriodEnd(
            $newPeriodStart,
            $subscription->billing_cycle,
        );

        // If subscription is still in trial period, end it immediately by setting trial_ends_at to now
        // Otherwise, keep the existing trial_ends_at value
        $trialEnd = $subscription->trial_ends_at?->isFuture()
            ? now()
            : $subscription->trial_ends_at;

        return $subscription->update([
            'current_period_start' => $newPeriodStart,
            'current_period_end' => $newPeriodEnd,
            'status' => SubscriptionStatus::ACTIVE, // Ensure it's active after renewal
            'trial_ends_at' => $trialEnd,
        ]);
    }

    /**
     * Change subscription plan
     */
    public function changePlan(
        Subscription $subscription,
        Plan $newPlan,
        array $options = [],
    ): Subscription {
        if (! $subscription->isActive()) {
            throw new \Exception('Subscription is not active');
        }

        $billingCycle =
            $options['billing_cycle'] ?? $subscription->billing_cycle;
        $prorate = $options['prorate'] ?? false;

        DB::transaction(function () use (
            $subscription,
            $newPlan,
            $billingCycle,
            $prorate,
        ) {
            $updateData = [
                'plan_id' => $newPlan->id,
                'billing_cycle' => $billingCycle,
                'price' => $billingCycle === 'yearly'
                    ? $newPlan->price_yearly
                    : $newPlan->price_monthly,
            ];

            // If proration is enabled, adjust the current period
            if ($prorate) {
                $remainingDays = $subscription->daysUntilExpiry();
                if ($remainingDays > 0) {
                    $newPeriodEnd = $this->calculatePeriodEnd(
                        Carbon::now(),
                        $billingCycle,
                        $remainingDays,
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
        $expiredTrials = Subscription::where(
            'status',
            SubscriptionStatus::TRIAL,
        )
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
    private function calculatePeriodEnd(
        Carbon $startDate,
        string $billingCycle,
        int $additionalDays = 0,
    ): Carbon {
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
            'total_trial' => Subscription::where(
                'status',
                SubscriptionStatus::TRIAL,
            )->count(),
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
    private function createFeatureSnapshots(
        Subscription $subscription,
        Plan $plan,
    ): void {
        $planFeatures = $plan->features;

        foreach ($planFeatures as $planFeature) {
            SubscriptionFeature::create([
                'subscription_id' => $subscription->id,
                'plan_feature_id' => $planFeature->id,
                'feature_name' => $planFeature->feature_name,
                'value' => $planFeature->value,
                'brief_description' => $planFeature->brief_description,
                'order_column' => $planFeature->order_column ?? 1,
            ]);
        }
    }

    /**
     * Assign a plan to a user (admin manual assignment)
     * Cancels existing active subscriptions and creates a new one with specified status
     */
    public function assignPlanToUser(User $user, Plan $plan, array $options = []): Subscription
    {
        return DB::transaction(function () use ($user, $plan, $options) {
            // Cancel any existing active subscriptions
            Subscription::where('user_id', $user->id)
                ->whereIn('status', [SubscriptionStatus::ACTIVE, SubscriptionStatus::TRIAL])
                ->update([
                    'status' => SubscriptionStatus::CANCELLED,
                    'cancelled_at' => Carbon::now(),
                    'ends_at' => Carbon::now(),
                ]);

            // Prepare options for subscription creation
            $subscriptionOptions = [
                'billing_cycle' => $options['billing_cycle'] ?? 'monthly',
                'force_status' => $options['status'] ?? SubscriptionStatus::ACTIVE, // Force the admin-selected status
            ];

            // Handle trial if specified
            if (isset($options['trial_ends_at']) && $options['trial_ends_at']) {
                $trialDays = Carbon::parse($options['trial_ends_at'])->diffInDays(Carbon::now());
                $subscriptionOptions['trial_days'] = $trialDays;
            }

            // Create new subscription
            $subscription = $this->createSubscription($user, $plan, $subscriptionOptions);

            // Add metadata for manual assignment
            $subscription->update([
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'assigned_by' => $options['assigned_by'] ?? auth()->id(),
                    'assigned_at' => Carbon::now()->toISOString(),
                    'assignment_type' => 'manual',
                    'notes' => $options['notes'] ?? null,
                ]),
            ]);

            return $subscription;
        });
    }
}
