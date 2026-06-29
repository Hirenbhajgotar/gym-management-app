<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Notifications\SubscriptionCancelled;
use App\Notifications\SubscriptionCreated;
use App\Notifications\SubscriptionExpired;
use App\Notifications\SubscriptionReactivated;
use App\Notifications\SubscriptionSuspended;
use Illuminate\Support\Facades\Log;

class SubscriptionObserver
{
    /**
     * Handle the Subscription "created" event.
     */
    public function created(Subscription $subscription): void
    {
        Log::info(
            "New subscription created for user {$subscription->user_id} with plan {$subscription->plan->name}",
        );

        // Send welcome notification
        try {
            $subscription->user->notify(new SubscriptionCreated($subscription));
        } catch (\Exception $e) {
            Log::error(
                "Failed to send subscription created notification: {$e->getMessage()}",
            );
        }
    }

    /**
     * Handle the Subscription "updated" event.
     */
    public function updated(Subscription $subscription): void
    {
        // Check if status changed
        if ($subscription->wasChanged('status')) {
            $this->handleStatusChange($subscription);
        }
    }

    /**
     * Handle the Subscription "deleted" event.
     */
    public function deleted(Subscription $subscription): void
    {
        Log::info(
            "Subscription {$subscription->id} deleted for user {$subscription->user_id}",
        );
    }

    /**
     * Handle status changes
     */
    private function handleStatusChange(Subscription $subscription): void
    {
        $oldStatus = $subscription->getOriginal('status');
        $newStatus = $subscription->status;

        Log::info(
            "Subscription {$subscription->id} status changed from {$oldStatus->value} to {$newStatus->value}",
        );

        try {
            match ($newStatus) {
                SubscriptionStatus::CANCELLED => $subscription->user->notify(
                    new SubscriptionCancelled($subscription),
                ),
                SubscriptionStatus::EXPIRED => $subscription->user->notify(
                    new SubscriptionExpired($subscription),
                ),
                SubscriptionStatus::SUSPENDED => $subscription->user->notify(
                    new SubscriptionSuspended($subscription),
                ),
                SubscriptionStatus::ACTIVE => $this->handleReactivation(
                    $subscription,
                ),
                default => null,
            };
        } catch (\Exception $e) {
            Log::error(
                "Failed to send subscription status change notification: {$e->getMessage()}",
            );
        }
    }

    /**
     * Handle subscription reactivation
     */
    private function handleReactivation(Subscription $subscription): void
    {
        $oldStatus = $subscription->getOriginal('status');

        // Only send reactivation notification if it was suspended
        if ($oldStatus === SubscriptionStatus::SUSPENDED) {
            $subscription->user->notify(
                new SubscriptionReactivated($subscription),
            );
        }
    }
}
