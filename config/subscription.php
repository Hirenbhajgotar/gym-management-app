<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Subscription Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for subscription management including
    | grace periods, trial settings, and other subscription-related options.
    |
    */

    'grace_period_days' => env('SUBSCRIPTION_GRACE_PERIOD_DAYS', 7),

    'trial_days' => env('SUBSCRIPTION_TRIAL_DAYS', 14),

    'currency' => env('SUBSCRIPTION_CURRENCY', 'INR'),

    'billing_cycles' => ['monthly', 'yearly'],

    'statuses' => [
        'trial' => 'trial',
        'active' => 'active',
        'cancelled' => 'cancelled',
        'expired' => 'expired',
        'suspended' => 'suspended',
    ],
];
