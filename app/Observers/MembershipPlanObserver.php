<?php

namespace App\Observers;

use App\Models\MembershipPlan;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;

class MembershipPlanObserver
{
    /**
     * Handle the MembershipPlan "created" event.
     */
    public function created(MembershipPlan $membershipPlan): void
    {
        $recipient = $membershipPlan->gym->owner() ?? auth()->user();

        // Skip sending notification if no recipient is available (e.g., during seeding)
        if (! $recipient) {
            return;
        }

        Notification::make()
            ->title('New Subscription Plan Added')
            ->body(
                "Subscription Plan '{$membershipPlan->name}' has been added successfully.",
            )
            ->success()
            ->sendToDatabase($recipient);

        event(new DatabaseNotificationsSent($recipient));
    }

    /**
     * Handle the MembershipPlan "updated" event.
     */
    public function updated(MembershipPlan $membershipPlan): void
    {
        //
    }

    /**
     * Handle the MembershipPlan "deleted" event.
     */
    public function deleted(MembershipPlan $membershipPlan): void
    {
        $recipient = $membershipPlan->gym->owner() ?? auth()->user();

        // Skip sending notification if no recipient is available (e.g., during seeding)
        if (! $recipient) {
            return;
        }

        $planName = $membershipPlan->name;

        if ($recipient->is(auth()->user())) {
            Notification::make()
                ->title("{$planName} removed")
                ->body(
                    "Subscription Plan '{$planName}' has been removed successfully.",
                )
                ->success()
                ->send();
        } elseif ($recipient->isNot(auth()->user())) {
            Notification::make()
                ->title("{$planName} removed")
                ->body(
                    "Subscription Plan '{$planName}' has been removed successfully.",
                )
                ->danger()
                ->sendToDatabase($recipient)
                ->send();
            event(new DatabaseNotificationsSent($recipient));
        }
    }

    /**
     * Handle the MembershipPlan "restored" event.
     */
    public function restored(MembershipPlan $membershipPlan): void
    {
        //
    }

    /**
     * Handle the MembershipPlan "force deleted" event.
     */
    public function forceDeleted(MembershipPlan $membershipPlan): void
    {
        //
    }
}
