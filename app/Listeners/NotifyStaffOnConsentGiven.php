<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\DefaultRole;
use App\Events\BiometricConsentGiven;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;

class NotifyStaffOnConsentGiven
{
    public function handle(BiometricConsentGiven $event): void
    {
        $gymMember = $event->gymMember;

        // Notify gym owner / assigned staff via Filament database notification
        // Find gym owner — user who owns this gym
        $owner = $gymMember->gym->users()
            ->wherePivot('role', DefaultRole::GYM_OWNER->value)
            ->first();

        if ($owner) {
            Notification::make()
                ->title($gymMember->name . ' gave biometric consent')
                ->body('Member is now ready for biometric enrollment.')
                ->success()
                ->sendToDatabase($owner);

            event(new DatabaseNotificationsSent($owner));
        }
    }
}
