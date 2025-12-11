<?php

use App\Enums\DefaultRole;

return [
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
