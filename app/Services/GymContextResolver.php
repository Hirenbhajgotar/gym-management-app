<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Gym;
use App\Models\User;
use App\Notifications\Contracts\HasGymContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

/**
 * Resolves the gym ID from notification context.
 *
 * Handles the chain of responsibility for determining which gym
 * a notification belongs to, supporting multiple resolution strategies.
 */
class GymContextResolver
{
    /**
     * Resolve the gym ID from the given notifiable and notification.
     *
     * Resolution order:
     * 1. Notification implements HasGymContext interface
     * 2. Notifiable has a gym_id property
     * 3. Notifiable has a gym relationship
     * 4. Notifiable is a User with exactly one gym (fallback)
     */
    public function resolve(mixed $notifiable, Notification $notification): ?int
    {
        // 1. Check for a getGymId method on the notification
        if ($notification instanceof HasGymContext) {
            $gymId = $notification->getGymId($notifiable);

            if ($gymId) {
                return $gymId;
            }
        }

        // Ensure notifiable is an object for property access
        if (! is_object($notifiable)) {
            return null;
        }

        // 2. Check the notifiable itself
        if (property_exists($notifiable, 'gym_id')) {
            return is_numeric($notifiable->gym_id) ? (int) $notifiable->gym_id : null;
        }

        // 3. Check the notifiable's gym relationship
        if (property_exists($notifiable, 'gym') && $notifiable->gym instanceof Model) {
            $gymId = $notifiable->gym->getKey();

            return is_numeric($gymId) ? (int) $gymId : null;
        }

        // 4. Handle User model with tenants as a fallback
        if ($notifiable instanceof User) {
            return $this->resolveFromUser($notifiable);
        }

        return null;
    }

    /**
     * Resolve gym ID from a User model's gym relationships.
     */
    private function resolveFromUser(User $user): ?int
    {
        if ($user->gyms()->count() === 1) {
            /** @var Gym $gym */
            $gym = $user->gyms()->first();

            return $gym->id;
        }

        return null;
    }
}
