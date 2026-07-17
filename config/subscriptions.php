<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Subscription / Billing configuration
    |--------------------------------------------------------------------------
    */

    // Number of days a paid (non-trial) subscription keeps feature access
    // after its period ends, before being flipped to EXPIRED.
    'grace_days' => 7,

    // Default billing cycle used when none is explicitly chosen.
    'default_billing_cycle' => 'yearly',

    // When true, upgrades charge only the prorated difference for the
    // remaining time on the current plan. When false, the full new-plan
    // price is charged (legacy behaviour).
    'proration_enabled' => false,

    // Allowed difference (in RUPEES) between the captured amount and order
    // amount before a payment is held for manual review. Stored amounts are in
    // rupees (decimal(10,2)), so 100 = ₹100.00 tolerance.
    'amount_mismatch_tolerance' => 100,

    // How many days back the scheduled app:reconcile-subscriptions command
    // looks for completed-but-unprovisioned payments.
    'reconcile_window_days' => 7,
];
