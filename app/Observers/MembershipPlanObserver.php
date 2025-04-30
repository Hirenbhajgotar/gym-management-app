<?php

namespace App\Observers;

use App\Models\MembershipPlan;
use Filament\Notifications\Notification;

class MembershipPlanObserver
{
    /**
     * Handle the MembershipPlan "created" event.
     */
    public function created(MembershipPlan $membershipPlan): void
    {
        Notification::make()
            ->title('New Subscription Plan Added')
            ->body("Subscription Plan '{$membershipPlan->name}' has been added successfully.")
            ->success()
            ->sendToDatabase($membershipPlan?->owner ?? auth()->user())
            ->send();
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
        $planName = $membershipPlan->name;
        Notification::make()
            ->title("{$planName} removed")
            ->body("Subscription Plan '{$planName}' has been removed successfully.")
            ->danger()
            ->sendToDatabase($membershipPlan?->owner ?? auth()->user())
            ->send();
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
