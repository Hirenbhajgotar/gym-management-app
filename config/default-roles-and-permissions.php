<?php

// NOTE: This file is no longer the source of truth. Role-permission mappings come from
// HasFilamentPermissions::defaultRolePermissions() on each Filament class. Keep for reference only.

use App\Enums\DefaultRole;

return [
    DefaultRole::GYM_OWNER->value => [],

    DefaultRole::MANAGER->value => [
        'user', 'trainer', 'receptionist', 'gym_member', 'gym', 'membership_plan', 'attendance', 'payment', 'lead',
    ],

    DefaultRole::RECEPTIONIST->value => [
        'gym_member', 'membership_plan', 'attendance', 'payment', 'lead',
    ],

    DefaultRole::TRAINER->value => [
        'attendance', 'gym_member',
    ],

    'default' => [],
];
