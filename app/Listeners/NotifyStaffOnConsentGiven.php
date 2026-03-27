<?php

namespace App\Listeners;

use App\Events\BiometricConsentGiven;
use Filament\Notifications\Notification;

class NotifyStaffOnConsentGiven
{
    public function handle(BiometricConsentGiven $event): void
    {
        $gymMember = $event->gymMember;

        // Notify gym owner / assigned staff via Filament database notification
        // Find gym owner — user who owns this gym
        $gymOwners = $gymMember->gym->users()
            ->wherePivot('role', 'owner')
            ->get();

        foreach ($gymOwners as $owner) {
            Notification::make()
                ->title($gymMember->name . ' gave biometric consent')
                ->body('Member is now ready for biometric enrollment.')
                ->success()
                ->sendToDatabase($owner);
        }
    }
}
