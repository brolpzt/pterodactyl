<?php

return [
    'manual_enabled' => env('BILLING_MANUAL_ENABLED', true),
    'stripe_enabled' => env('BILLING_STRIPE_ENABLED', false),
    'pix_enabled' => env('BILLING_PIX_ENABLED', false),
    'currency' => 'USD',

    /*
    |--------------------------------------------------------------------------
    | Period discounts (multiplier of base hourly rate)
    | monthly: 730 hours, quarterly: 2190, semi_annually: 4380, annually: 8760
    |--------------------------------------------------------------------------
    */
    'period_discounts' => [
        'monthly' => 0.75,      // 25% off
        'quarterly' => 0.70,   // 30% off
        'semi_annually' => 0.65, // 35% off
        'annually' => 0.60,    // 40% off
    ],

    'period_days' => [
        'monthly' => 30,
        'quarterly' => 90,
        'semi_annually' => 180,
        'annually' => 365,
    ],
];
