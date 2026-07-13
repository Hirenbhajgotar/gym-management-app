<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\DefaultRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\AccountActivatedNotification;
use App\Notifications\AccountDeactivatedNotification;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Deferred observer for User account-status notifications.
 *
 * Implements ShouldHandleEventsAfterCommit so the activation / deactivation
 * notifications only fire after the enclosing database transaction commits
 * (and are skipped on rollback). The synchronous, transaction-critical work
 * (is_active sync + gym-owner cascade deletion) lives in User.
 */
class UserNotificationObserver implements ShouldHandleEventsAfterCommit
{
    public function updated(User $user): void
    {
        if ($user->wasChanged('status')) {
            $this->handleAccountStatusChange($user);
        }
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
        try {
            $user->notify(new AccountActivatedNotification($user));

            if (Auth::check() && ! app()->runningInConsole()) {
                Notification::make()
                    ->success()
                    ->title('Account Activated')
                    ->body('User has been notified.')
                    ->send();
            }
        } catch (Exception $e) {
            Log::error('Failed to send notification', ['user' => $user, 'error' => $e->getMessage()]);

            if (Auth::check() && ! app()->runningInConsole()) {
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
        try {
            $user->notify(new AccountDeactivatedNotification($user));

            if (Auth::check() && ! app()->runningInConsole()) {
                Notification::make()
                    ->warning()
                    ->title('Account Deactivated')
                    ->body('User has been notified.')
                    ->send();
            }
        } catch (Exception $e) {
            Log::error('Failed to send notification', ['user' => $user, 'error' => $e->getMessage()]);

            if (Auth::check() && ! app()->runningInConsole()) {
                Notification::make()
                    ->danger()
                    ->title('Failed to send notification')
                    ->body($e->getMessage())
                    ->send();
            }
        }
    }
}
