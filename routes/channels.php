<?php

declare(strict_types=1);

use App\Enums\DefaultRole;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Reports channel - authorize users for their gym's reports
Broadcast::channel('reports.{gymId}', function ($user, $gymId) {
    // Check if user has access to this gym
    return $user->gyms()->where('gyms.id', $gymId)->exists();
});

// Real-time attendance channel - authorize users for their gym's attendance
Broadcast::channel('gym.{gymId}.attendance', function ($user, $gymId) {
    return $user->gyms()->where('gyms.id', $gymId)->exists();
});

// Door controller events channel - authorize users for their gym's door events
Broadcast::channel('gym.{gymId}.door', function ($user, $gymId) {
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
