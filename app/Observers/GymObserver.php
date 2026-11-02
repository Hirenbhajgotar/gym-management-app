<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Gym;
use App\Services\GymSettingService;
use Exception;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GymObserver
{
    /**
     * Handle the Gym "created" event.
     */
    public function created(Gym $gym): void
    {
        resolve(GymSettingService::class)->createDefaultSettings($gym);

        DB::afterCommit(function () use ($gym): void {
            $recipient = $gym->owner() ?? Auth::user();

            if (! $recipient) {
                return;
            }

            Notification::make()
                ->title('New Gym Added')
                ->body("Gym '{$gym->name}' has been added successfully.")
                ->success()
                ->sendToDatabase($recipient)
                ->send();

            event(new DatabaseNotificationsSent($recipient));
        });
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
     * Runs synchronously before the row is deleted, so the relations still exist. The
     * biometric guard and the Twilio cleanup live in the synchronous `Gym::deleting()`
     * model listener; this observer no longer defers to after-commit (which made the
     * cascade lazy-load relations that the database had already cascade-deleted).
     */
    public function deleting(Gym $gym): void
    {
        $gym->loadMissing(['gymMembers', 'leads', 'socialConfigs', 'businessDetail', 'address']);

        try {
            Log::info('Starting cascade deletion for gym', [
                'gym_id' => $gym->id,
                'gym_name' => $gym->name,
            ]);

            // 1. Delete all gym members and their associated data
            foreach ($gym->gymMembers as $member) {
                // The tenant guard has already run: either the member holds no obligation,
                // or the audited purge exported the register before overriding the guard.
                $member->deleteWithinAuditedTenantCascade(); // GymMemberObserver handles related data
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

            // 7. Twilio resources and the WhatsApp account are released by
            // Gym::cleanupTwilioResourcesBeforeDeletion(), which runs synchronously before
            // the DELETE. By the time this after-commit cascade runs, the gym_id cascade has
            // already removed the account, so a cleanup here would be a silent no-op.

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
        } catch (Exception $e) {
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
        DB::afterCommit(function () use ($gym): void {
            $recipient = $gym->owner() ?? Auth::user();

            if (! $recipient) {
                return;
            }

            $gymName = $gym->name;
            Notification::make()
                ->title("{$gymName} removed")
                ->body("Gym '{$gymName}' has been removed successfully.")
                ->danger()
                ->sendToDatabase($recipient)
                ->send();

            event(new DatabaseNotificationsSent($recipient));
        });
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
