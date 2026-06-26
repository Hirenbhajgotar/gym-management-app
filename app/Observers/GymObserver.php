<?php

namespace App\Observers;

use Illuminate\Support\Facades\Log;
use App\Models\Gym;
use App\Services\GymSettingService;
use App\Services\TwilioAccountManager;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;

class GymObserver
{
    /**
     * Handle the Gym "created" event.
     */
    public function created(Gym $gym): void
    {
        resolve(GymSettingService::class)->createDefaultSettings($gym);

        $recipient = $gym->owner() ?? auth()->user();

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
     * Handle the Gym "deleting" event.
     *
     * This handles cascade deletion of all gym-related data before the gym is deleted.
     */
    public function deleting(Gym $gym): void
    {
        try {
            Log::info('Starting cascade deletion for gym', [
                'gym_id' => $gym->id,
                'gym_name' => $gym->name,
            ]);

            // 1. Delete all gym members and their associated data
            foreach ($gym->gymMembers as $member) {
                $member->delete(); // GymMemberObserver will handle related data
            }

            // 2. Delete all membership plans
            $gym->membershipPlans()->delete();

            // 3. Delete all trainers
            $gym->trainers()->delete();

            // 4. Delete all leads and their follow-ups
            foreach ($gym->leads as $lead) {
                $lead->followUps()->delete();
                $lead->delete();
            }

            // 5. Delete all attendances
            $gym->attendances()->delete();

            // 6. Delete gym settings
            $gym->settings()->delete();

            // 7. Clean up Twilio resources (subaccount, phone numbers, messaging service)
            // and delete WhatsApp Account
            if ($gym->whatsAppAccount) {
                try {
                    resolve(TwilioAccountManager::class)->cleanupTenantResources($gym->id);
                } catch (\Exception $e) {
                    Log::warning('Twilio cleanup failed during gym deletion, continuing with local cleanup', [
                        'gym_id' => $gym->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                // Delete phone numbers first (local records)
                $gym->whatsAppAccount->phoneNumber()->delete();
                $gym->whatsAppAccount->delete();
            }

            // 8. Delete social configs
            $gym->socialConfigs()->delete();

            // 9. Delete business details
            if ($gym->businessDetail) {
                $gym->businessDetail->delete();
            }

            // 10. Delete address
            if ($gym->address) {
                $gym->address->delete();
            }

            // 11. Detach all users from this gym (gym_user pivot table)
            $gym->users()->detach();

            Log::info('Successfully completed cascade deletion for gym', [
                'gym_id' => $gym->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cascade delete gym data', [
                'gym_id' => $gym->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle the Gym "deleted" event.
     */
    public function deleted(Gym $gym): void
    {
        $recipient = $gym->owner() ?? auth()->user();

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
