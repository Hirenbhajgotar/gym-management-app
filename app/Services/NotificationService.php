<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DefaultRole;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

final class NotificationService
{
    /**
     * Send a notification to all admins via Filament's notification system.
     */
    public static function notifyAdmins(string $title, string $message, string $type = 'info', bool $isEventDispatched = true, ?User $user = null): void
    {
        if (blank($title) || blank($message)) {
            return;
        }

        $targets = $user
            ? collect([$user])
            : self::adminTargets();

        foreach ($targets as $target) {
            $notification = Notification::make()
                ->title($title)
                ->body($message);

            $notification->{$type}();

            $notification
                ->sendToDatabase($target, isEventDispatched: $isEventDispatched)
                ->send();
        }
    }

    /**
     * Resolve the users that should receive an admin notification.
     *
     * Notifying administrators is best-effort: if the target role has not
     * been seeded (e.g. in a test environment) we simply notify no one
     * rather than crashing the calling flow.
     *
     * @return Collection<int, User>
     */
    private static function adminTargets(): Collection
    {
        try {
            return User::query()->role(DefaultRole::SUPER_ADMIN->value)->get();
        } catch (RoleDoesNotExist) {
            /** @var Collection<int, User> $empty */
            $empty = collect();

            return $empty;
        }
    }
}
