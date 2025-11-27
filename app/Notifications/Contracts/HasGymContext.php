<?php

namespace App\Notifications\Contracts;

interface HasGymContext
{
    /**
     * Get the Gym ID for the notification context.
     *
     * @param mixed $notifiable
     * @return int|null
     */
    public function getGymId(mixed $notifiable): ?int;
}
