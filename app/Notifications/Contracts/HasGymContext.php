<?php

declare(strict_types=1);

namespace App\Notifications\Contracts;

interface HasGymContext
{
    /**
     * Get the Gym ID for the notification context.
     */
    public function getGymId(mixed $notifiable): ?int;
}
