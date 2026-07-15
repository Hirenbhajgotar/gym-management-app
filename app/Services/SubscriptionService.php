<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionFeature;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /**
     * Create a new subscription for a user
     */
    public function createSubscription(User $user, Plan $plan, array $options = []): Subscription
    {
        return DB::transaction(function () use ($user, $plan, $options) {
            // Lock user first (consistent order)
            $lockedUser = User::query()->where('id', $user->id)
                ->lockForUpdate()
                ->first();

            throw_unless($lockedUser, Exception::class, 'User not found');

            // Check if user already has an active subscription
            $activeSubscription = Subscription::query()->where('user_id', $lockedUser->id)
                ->active()
                ->lockForUpdate()
                ->first();

            throw_if($activeSubscription, Exception::class, 'User already has an active subscription');

            // Lock plan and load features (prevent concurrent plan updates)
            $lockedPlan = Plan::with('features')
                ->where('id', $plan->id)
                ->sharedLock()
                ->first();

            throw_unless($lockedPlan, Exception::class, 'Plan not found');

            // Check if user can get a free trial
            $hasUsedTrial = $lockedUser->hasUsedTrial();

            /** @var string $billingCycle */
            $billingCycle = $options['billing_cycle'] ?? 'yearly';

            $trialDays = 0;
            if (isset($options['trial_days'])) {
                $trialDays = $options['trial_days'];
            } elseif (! $hasUsedTrial && $lockedPlan->trial_days) {
                $trialDays = $lockedPlan->trial_days;
            }

            if (isset($options['force_status']) && $options['force_status'] !== SubscriptionStatus::TRIAL) {
                $trialDays = 0;
            }

            // Determine status - allow override for admin assignments
            $status = $options['force_status'] ?? ($trialDays > 0 ? SubscriptionStatus::TRIAL : SubscriptionStatus::ACTIVE);

            $metadata = null;
            if (isset($options['metadata']) && filled($options['metadata'])) {
                $metadata = $options['metadata'];
            }
            $priceResolver = resolve(PriceResolver::class)->resolve($lockedPlan, $billingCycle);

            // Create subscription
            $subscription = Subscription::query()->create([
                'user_id' => $lockedUser->id,
                'plan_id' => $lockedPlan->id,
                'status' => $status,
                'billing_cycle' => $billingCycle,
                'price' => $priceResolver['amount'],
                'currency' => $priceResolver['currency'],
                'trial_ends_at' => $trialDays > 0 ? Date::now()->addDays($trialDays) : null,
                'current_period_start' => Date::now(),
                'current_period_end' => $this->calculatePeriodEnd(
                    Date::now(),
                    $billingCycle,
                ),
                'metadata' => $metadata,
            ]);

            // Create feature snapshots
            $this->createFeatureSnapshots($subscription, $lockedPlan);

            return $subscription;
        }, attempts: 3); // Retry 3 times for deadlock handling
    }

    /**
     * Create a pending subscription for failed/incomplete payments
     */
    public function createPendingSubscription(User $user, Plan $plan, array $options = []): Subscription
    {
        /** @var string $billingCycle */
        $billingCycle = $options['billing_cycle'] ?? 'monthly';

        return DB::transaction(function () use ($user, $plan, $billingCycle) {
            // Create subscription with PENDING status
            $subscription = Subscription::query()->create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::PENDING,
                'billing_cycle' => $billingCycle,
                'price' => $billingCycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly,
                'currency' => $plan->currency,
                'trial_ends_at' => null, // No trial for pending subscriptions
                'current_period_start' => Date::now(),
                'current_period_end' => $this->calculatePeriodEnd(Date::now(), $billingCycle),
            ]);

            // Create feature snapshots
            $this->createFeatureSnapshots($subscription, $plan);

            return $subscription;
        });
    }

    /**
     * Activate a pending subscription after successful payment
     */
    public function activateSubscription(Subscription $subscription): bool
    {
        throw_if($subscription->status !== SubscriptionStatus::PENDING, Exception::class, 'Only pending subscriptions can be activated');

        return $subscription->update([
            'status' => SubscriptionStatus::ACTIVE,
            'current_period_start' => Date::now(),
            'current_period_end' => $this->calculatePeriodEnd(
                Date::now(),
                $subscription->billing_cycle,
            ),
        ]);
    }

    /**
     * Cancel a subscription
     */
    public function cancelSubscription(Subscription $subscription, bool $immediately = false): bool
    {
        return DB::transaction(function () use ($subscription, $immediately) {
            $lockedSubscription = Subscription::query()->where('id', $subscription->id)->lockForUpdate()->first();

            throw_unless($lockedSubscription && $lockedSubscription->isActive(), Exception::class, 'Subscription is not active');

            $trialEnd = $lockedSubscription->trial_ends_at?->isFuture()
                ? now()
                : $lockedSubscription->trial_ends_at;

            $updateData = [
                'status' => SubscriptionStatus::CANCELLED,
                'cancelled_at' => Date::now(),
                'trial_ends_at' => $trialEnd ?? null,
            ];

            if ($immediately) {
                $updateData['ends_at'] = Date::now();
                $updateData['current_period_end'] = Date::now();
            } else {
                $updateData['ends_at'] = $lockedSubscription->current_period_end;
            }

            return $lockedSubscription->update($updateData);
        });
    }

    /**
     * Renew a subscription. The charged amount is snapshotted onto the
     * subscription so the stored price always reflects what was actually paid
     * (G1), and the new period starts from the later of the current end or now
     * so lapsed subscriptions do not lose the expired interval (G5).
     */
    public function renewSubscription(Subscription $subscription, ?float $chargedAmount = null): bool
    {
        return DB::transaction(function () use ($subscription, $chargedAmount) {
            $lockedSubscription = Subscription::query()
                ->with('plan')
                ->where('id', $subscription->id)
                ->lockForUpdate()
                ->first();

            throw_unless($lockedSubscription, Exception::class, 'Subscription not found');

            $cycle = $lockedSubscription->billing_cycle;

            $newPeriodStart = ($lockedSubscription->current_period_end?->isFuture())
                ? $lockedSubscription->current_period_end
                : Date::now();

            $newPeriodEnd = $this->calculatePeriodEnd($newPeriodStart, $cycle);

            $trialEnd = $lockedSubscription->trial_ends_at?->isFuture()
                ? now()
                : $lockedSubscription->trial_ends_at;

            /** @var Plan $plan */
            $plan = $lockedSubscription->plan;
            $priceResolver = resolve(PriceResolver::class)->resolve($plan, $cycle);

            $lockedSubscription->update([
                'current_period_start' => $newPeriodStart,
                'current_period_end' => $newPeriodEnd,
                'status' => SubscriptionStatus::ACTIVE,
                'price' => $chargedAmount ?? $priceResolver['amount'],
                'currency' => $priceResolver['currency'],
                'trial_ends_at' => $trialEnd,
            ]);

            $this->syncFeatureSnapshots($lockedSubscription, $plan);

            return true;
        });
    }

    /**
     * Change subscription plan
     */
    public function changePlan(
        Subscription $subscription,
        Plan $newPlan,
        array $options = [],
    ): Subscription {
        throw_unless($subscription->isActive(), Exception::class, 'Subscription is not active');

        /** @var string $billingCycle */
        $billingCycle = $options['billing_cycle'] ?? $subscription->billing_cycle;
        $prorate = $options['prorate'] ?? false;

        DB::transaction(function () use ($subscription, $newPlan, $billingCycle, $prorate) {
            $priceResolver = resolve(PriceResolver::class)->resolve($newPlan, $billingCycle);

            $updateData = [
                'plan_id' => $newPlan->id,
                'billing_cycle' => $billingCycle,
                'price' => $priceResolver['amount'],
            ];

            // If proration is enabled, adjust the current period
            if ($prorate) {
                $remainingDays = $subscription->daysUntilExpiry();
                if ($remainingDays > 0) {
                    $newPeriodEnd = $this->calculatePeriodEnd(
                        Date::now(),
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
     * Check and update expired subscriptions.
     *
     * Paid (non-trial) subscriptions get a grace period: they stay ACTIVE and
     * keep feature access until grace_period_ends_at passes, at which point
     * they are flipped to EXPIRED. Trials are expired immediately.
     */
    public function checkExpiredSubscriptions(): int
    {
        $graceDays = config()->integer('subscriptions.grace_days', 0);

        // First, expire anything whose grace period has elapsed.
        $count = 0;
        $expired = Subscription::query()
            ->where('status', SubscriptionStatus::ACTIVE)
            ->whereNotNull('grace_period_ends_at')
            ->where('grace_period_ends_at', '<', Date::now())
            ->get();

        foreach ($expired as $subscription) {
            $this->expireSubscription($subscription);
            $count++;
        }

        // Then, subscriptions past their period with no grace set yet get one.
        if ($graceDays > 0) {
            $dueForGrace = Subscription::active()
                ->where('current_period_end', '<', Date::now())
                ->whereNull('grace_period_ends_at')
                ->get();

            foreach ($dueForGrace as $subscription) {
                $endsAt = $subscription->current_period_end;

                if ($endsAt !== null) {
                    $subscription->update([
                        'grace_period_ends_at' => $endsAt->copy()->addDays($graceDays),
                    ]);
                }
            }
        } else {
            $due = Subscription::active()
                ->where('current_period_end', '<', Date::now())
                ->whereNull('grace_period_ends_at')
                ->get();

            foreach ($due as $subscription) {
                $this->expireSubscription($subscription);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Check and expire expired trials
     */
    public function checkExpiredTrials(): int
    {
        $expiredTrials = Subscription::query()->where('status', SubscriptionStatus::TRIAL)
            ->where('trial_ends_at', '<', Date::now())
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
            'total_trial' => Subscription::query()->where('status', SubscriptionStatus::TRIAL)->count(),
            'total_cancelled' => Subscription::cancelled()->count(),
            'total_expired' => Subscription::expired()->count(),
            'total_suspended' => Subscription::suspended()->count(),
            'expiring_soon' => Subscription::active()
                ->where('current_period_end', '<=', Date::now()->addDays(7))
                ->count(),
        ];
    }

    /**
     * Create feature snapshots for a subscription
     */
    private function syncFeatureSnapshots(Subscription $subscription, Plan $plan): void
    {
        $subscription->features()->delete();
        $this->createFeatureSnapshots($subscription, $plan);
    }

    private function createFeatureSnapshots(Subscription $subscription, Plan $plan): void
    {
        $features = $plan->features->map(fn ($planFeature) => [
            'subscription_id' => $subscription->id,
            'plan_feature_id' => $planFeature->id,
            'feature_name' => $planFeature->feature_name,
            'value' => $planFeature->value,
            'brief_description' => $planFeature->brief_description,
            'order_column' => $planFeature->order_column ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        if (filled($features)) {
            SubscriptionFeature::query()->insert($features);
        }
    }

    /**
     * Assign a plan to a user (admin manual assignment)
     * Cancels existing active subscriptions and creates a new one with specified status
     */
    public function assignPlanToUser(User $user, Plan $plan, array $options = []): Subscription
    {
        return DB::transaction(function () use ($user, $plan, $options) {
            // Lock user first to prevent race conditions and consistent locking order (User -> Plan)
            $lockedUser = User::query()->where('id', $user->id)->lockForUpdate()->first();

            throw_unless($lockedUser, Exception::class, 'User not found');

            $lockedPlan = Plan::with('features')
                ->where('id', $plan->id)
                ->sharedLock()
                ->first();

            throw_unless($lockedPlan, Exception::class, 'Plan not found');

            $activeSubscriptions = Subscription::query()->where('user_id', $lockedUser->id)
                ->active()
                ->lockForUpdate()
                ->get();

            if ($activeSubscriptions->isNotEmpty()) {
                $cancelledIds = $activeSubscriptions->pluck('id')->toArray();

                // Cancel any existing active/trial subscriptions
                Subscription::query()->whereIn('id', $cancelledIds)
                    ->update([
                        'status' => SubscriptionStatus::CANCELLED,
                        'cancelled_at' => Date::now(),
                        'ends_at' => Date::now(),
                        'metadata' => [
                            'cancelled_by' => 'admin',
                            'description' => 'This plan was cancelled because a new plan was assigned manually by a super-admin.',
                        ],
                    ]);
            }

            // Prepare options for subscription creation
            $subscriptionOptions = [
                'billing_cycle' => $options['billing_cycle'] ?? 'monthly',
                'force_status' => $options['status'] ?? SubscriptionStatus::ACTIVE, // Force the admin-selected status
            ];

            // Handle trial if specified
            if (isset($options['trial_ends_at']) && $options['trial_ends_at']) {
                $trialDays = Date::parse($options['trial_ends_at'])->diffInDays(Date::now());
                $subscriptionOptions['trial_days'] = $trialDays;
            }

            // Add metadata for manual assignment
            $subscriptionOptions['metadata'] = [
                'assigned_by' => $options['assigned_by'] ?? auth()->id(),
                'assigned_at' => Date::now()->toISOString(),
                'assignment_type' => 'manual',
                'notes' => $options['notes'] ?? null,
            ];

            // Create new subscription
            // Note: createSubscription also locks the user, which is safe within the same transaction/connection.
            $subscription = $this->createSubscription($lockedUser, $lockedPlan, $subscriptionOptions);

            return $subscription;
        }, attempts: 3); // Retry 3 times for deadlock handling
    }
}
