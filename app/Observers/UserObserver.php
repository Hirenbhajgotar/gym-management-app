<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\DefaultRole;
use App\Enums\UserStatus;
use App\Helpers\ParserHelper;
use App\Models\Gym;
use App\Models\User;
use App\Notifications\AccountActivatedNotification;
use App\Notifications\AccountDeactivatedNotification;
use Filament\Notifications\Notification;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    /**
     * Handle the User "saving" event.
     */
    public function saving(User $user): void
    {
        if ($user->isDirty('status')) {
            $user->is_active = $user->status === UserStatus::ACTIVE;
        }
    }

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $this->clearCache();
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        $this->clearCache();

        if ($user->wasChanged('status')) {
            $this->handleAccountStatusChange($user);
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        $this->clearCache();
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        $this->clearCache();
    }

    /**
     * Handle the User "deleting" event.
     *
     * This observer handles cascade deletion of all related data when a gym_owner is deleted.
     * It ensures complete data cleanup including:
     * - All gyms owned by the user (which triggers GymObserver to clean gym-related data)
     * - User subscriptions, payments, and billing data
     * - Employee details and addresses
     * - Roles and permissions assignments
     * - Notification templates, settings, and logs
     * - Leads, follow-ups, feedback, and error reports
     * - Payments, exports, imports, and billing data
     * - Social media configurations and other user-specific data
     * - Pivot table relationships
     */
    public function forceDeleting(User $user): void
    {
        $this->clearCache();

        // Only cascade delete if user is a gym_owner
        if (! $user->hasRole(DefaultRole::GYM_OWNER->value)) {
            return;
        }

        try {
            DB::beginTransaction();

            Log::info('Starting cascade deletion for gym_owner', [
                'user_id' => $user->id,
                'user_email' => $user->email,
            ]);

            // 1. Delete all gyms owned by this user
            // Note: GymObserver will handle deletion of gym-related data
            // (members, trainers, settings, social configs, etc.)
            $ownedGyms = Gym::whereHas('users', function (Builder $query) use ($user) {
                $query->where('users.id', $user->id)
                    ->where('gym_user.role', DefaultRole::GYM_OWNER->value);
            })->get();

            foreach ($ownedGyms as $gym) {
                Log::info('Deleting gym', [
                    'gym_id' => $gym->id,
                    'gym_name' => $gym->name,
                    'owner_id' => $user->id,
                ]);
                $gym->delete();
            }

            // 2. Delete all subscriptions
            $user->subscriptions()->delete();
            Log::info('Deleted user subscriptions', ['user_id' => $user->id]);

            // 3. Delete employee details if exists
            if ($user->employeeDetails) {
                $user->employeeDetails->delete();
                Log::info('Deleted employee details', ['user_id' => $user->id]);
            }

            // 4. Delete address if exists
            if ($user->address) {
                $user->address->delete();
                Log::info('Deleted user address', ['user_id' => $user->id]);
            }

            // 5. Detach from any gyms (gym_user pivot table)
            $user->gyms()->detach();
            Log::info('Detached user from all gyms', ['user_id' => $user->id]);

            // 6. Remove all role assignments (model_has_roles pivot table)
            // This is handled by Spatie's HasRoles trait, but we'll be explicit
            DB::table('model_has_roles')
                ->where('model_type', User::class)
                ->where('model_id', $user->id)
                ->delete();
            Log::info('Deleted role assignments', ['user_id' => $user->id]);

            // 7. Remove all permission assignments (model_has_permissions pivot table)
            DB::table('model_has_permissions')
                ->where('model_type', User::class)
                ->where('model_id', $user->id)
                ->delete();
            Log::info('Deleted permission assignments', ['user_id' => $user->id]);

            // 8. Delete notification logs
            DB::table('notification_logs')
                ->where('user_id', $user->id)
                ->delete();
            Log::info('Deleted notification logs', ['user_id' => $user->id]);

            // 9. Delete all payments associated with this user
            DB::table('payments')
                ->where('user_id', $user->id)
                ->delete();
            Log::info('Deleted user payments', ['user_id' => $user->id]);

            // 10. Delete tenant social configs assigned to this user
            DB::table('tenant_social_configs')
                ->where('assigned_user_id', $user->id)
                ->delete();
            Log::info('Deleted tenant social configs', ['user_id' => $user->id]);

            // 11. Delete notification templates owned by this user
            DB::table('notification_templates')
                ->where('gym_owner_id', $user->id)
                ->delete();
            Log::info('Deleted notification templates', ['user_id' => $user->id]);

            // 12. Delete notification settings owned by this user
            DB::table('notification_settings')
                ->where('gym_owner_id', $user->id)
                ->delete();
            Log::info('Deleted notification settings', ['user_id' => $user->id]);

            // 13. Delete leads created or updated by this user
            DB::table('leads')
                ->where('created_by', $user->id)
                ->orWhere('updated_by', $user->id)
                ->delete();
            Log::info('Deleted leads', ['user_id' => $user->id]);

            // 14. Delete follow-ups associated with this user
            DB::table('follow_ups')
                ->where('user_id', $user->id)
                ->delete();
            Log::info('Deleted follow-ups', ['user_id' => $user->id]);

            // 16. Delete error reports associated with this user
            DB::table('error_reports')
                ->where('user_id', $user->id)
                ->delete();
            Log::info('Deleted error reports', ['user_id' => $user->id]);

            // 17. Delete exports created by this user
            DB::table('exports')
                ->where('user_id', $user->id)
                ->delete();
            Log::info('Deleted exports', ['user_id' => $user->id]);

            // 18. Delete imports created by this user
            DB::table('imports')
                ->where('user_id', $user->id)
                ->delete();
            Log::info('Deleted imports', ['user_id' => $user->id]);

            DB::commit();

            Log::info('Successfully completed cascade deletion for gym_owner', [
                'user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to cascade delete gym_owner data', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function clearCache(): void
    {
        ParserHelper::clearCache(User::NAVIGATION_BADGE_CACHE_KEY);
        ParserHelper::clearCache(User::ALL_SUPER_ADMINS_CACHE_KEY);
    }

    private function handleAccountStatusChange(User $user): void
    {
        $isGymOwner = $user->gyms()->wherePivot('role', DefaultRole::GYM_OWNER->value)->exists();

        if (! $isGymOwner) {
            return;
        }

        if ($user->status === UserStatus::ACTIVE) {
            $this->sendAccountActivateNotification($user);
        } elseif ($user->status === UserStatus::INACTIVE) {
            $this->sendAccountDeactivateNotification($user);
        }
    }

    private function sendAccountActivateNotification(User $user): void
    {
        if ($user) {
            try {
                $user->notify(new AccountActivatedNotification($user));
                Notification::make()
                    ->success()
                    ->title('Account Activated')
                    ->body('User has been notified.')
                    ->send();
            } catch (\Exception $e) {
                Log::error('Failed to send notification', ['user' => $user, 'error' => $e->getMessage()]);
                Notification::make()
                    ->danger()
                    ->title('Failed to send notification')
                    ->body($e->getMessage())
                    ->send();
            }
        }
    }

    private function sendAccountDeactivateNotification(User $user): void
    {
        if ($user) {
            try {
                $user->notify(new AccountDeactivatedNotification($user));
                Notification::make()
                    ->warning()
                    ->title('Account Deactivated')
                    ->body('User has been notified.')
                    ->send();
            } catch (\Exception $e) {
                Log::error('Failed to send notification', ['user' => $user, 'error' => $e->getMessage()]);
                Notification::make()
                    ->danger()
                    ->title('Failed to send notification')
                    ->body($e->getMessage())
                    ->send();
            }
        }
    }
}
