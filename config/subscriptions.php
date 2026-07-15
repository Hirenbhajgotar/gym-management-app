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

    // Allowed difference (in paisa / minor units) between the amount Razorpay
    // reports as captured and the amount we created the order for, before a
    // payment is flagged for manual review instead of being provisioned.
    'amount_mismatch_tolerance' => 100,
];
