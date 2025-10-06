<?php

namespace App\Observers;

use App\Models\Gym;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;

class GymObserver
{
    /**
     * Handle the Gym "created" event.
     */
    public function created(Gym $gym): void
    {

        $gym->settings()->create([]);

        $recipient = $gym?->owner ?? auth()->user();

        if ($recipient) {
            Notification::make()
                ->title('New Gym Added')
                ->body("Gym '{$gym->name}' has been added successfully.")
                ->success()
                ->sendToDatabase($recipient)
                ->send();

            event(new DatabaseNotificationsSent($recipient));
        }
    }

    /**
     * Handle the Gym "updated" event.
     */
    public function updated(Gym $gym): void
    {
        //
    }

    /**
     * Handle the Gym "deleted" event.
     */
    public function deleted(Gym $gym): void
    {
        $recipient = $gym?->owner ?? auth()->user();

        if ($recipient) {
            $gymName = $gym->name;
            Notification::make()
                ->title("{$gymName} removed")
                ->body("Gym '{$gymName}' has been removed successfully.")
                ->danger()
                ->sendToDatabase($recipient)
                ->send();

            event(new DatabaseNotificationsSent($recipient));
        }
    }

    /**
     * Handle the Gym "restored" event.
     */
    public function restored(Gym $gym): void
    {
        //
    }

    /**
     * Handle the Gym "force deleted" event.
     */
    public function forceDeleted(Gym $gym): void
    {
        //
    }
}
