<?php

declare(strict_types=1);

use App\Enums\DefaultRole;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int|string $id): bool {
    return $user->id === (int) $id;
});

// Reports channel - authorize active users with access to their gym's reports
Broadcast::channel('reports.{gymId}', function (User $user, int|string $gymId): bool {
    return $user->gyms()
        ->where('gyms.id', $gymId)
        ->wherePivot('active', true)
        ->exists();
});

// Real-time attendance channel - authorize active staff users for their gym's attendance
Broadcast::channel('gym.{gymId}.attendance', function (User $user, int|string $gymId): bool {
    return $user->gyms()
        ->where('gyms.id', $gymId)
        ->wherePivot('active', true)
        ->wherePivotIn('role', [
            DefaultRole::GYM_OWNER->value,
            DefaultRole::MANAGER->value,
            DefaultRole::RECEPTIONIST->value,
            DefaultRole::TRAINER->value,
        ])
        ->exists();
});

// Door controller events channel - authorize users for their gym's door events
Broadcast::channel('gym.{gymId}.door', function (User $user, int|string $gymId): bool {
    // return $user->gyms()->where('gyms.id', $gymId)->exists();
    return $user->gyms()
        ->where('gyms.id', $gymId)
        ->wherePivot('active', true)
        ->wherePivotIn('role', [
            DefaultRole::GYM_OWNER->value,
            DefaultRole::MANAGER->value,
            DefaultRole::RECEPTIONIST->value,
            DefaultRole::TRAINER->value,
        ])
        ->exists();
});
