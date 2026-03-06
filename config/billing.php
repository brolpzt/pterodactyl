<?php

return [
    'manual_enabled' => env('BILLING_MANUAL_ENABLED', true),
    'stripe_enabled' => env('BILLING_STRIPE_ENABLED', false),
    'pix_enabled' => env('BILLING_PIX_ENABLED', false),
    'currency' => 'USD',

    /*
    |--------------------------------------------------------------------------
    | Period days (used for pricing: hourly_rate * days * 24 = full price)
    | No discount applied - full price per period.
    |--------------------------------------------------------------------------
    */
    'period_days' => [
        'monthly' => 30,
        'quarterly' => 90,
        'semi_annually' => 180,
        'annually' => 365,
    ],
];
