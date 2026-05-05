<?php

return [
    'manual_enabled' => env('BILLING_MANUAL_ENABLED', false),
    'stripe_enabled' => env('BILLING_STRIPE_ENABLED', false),
    'stripe_secret_key' => env('BILLING_STRIPE_SECRET_KEY', null),
    'stripe_webhook_secret' => env('BILLING_STRIPE_WEBHOOK_SECRET', null),
    'stripe_success_url' => env('BILLING_STRIPE_SUCCESS_URL', null),
    'stripe_cancel_url' => env('BILLING_STRIPE_CANCEL_URL', null),
    'pix_enabled' => env('BILLING_PIX_ENABLED', false),
    'currency' => 'USD',

    /*
    |--------------------------------------------------------------------------
    | Exchange rates (USD base). Fetched daily via p:billing:fetch-exchange-rates.
    | API: ExchangeRate-API - https://www.exchangerate-api.com
    | Open (no key): https://open.er-api.com/v6/latest/USD
    | With key: https://v6.exchangerate-api.com/v6/{key}/latest/USD
    |--------------------------------------------------------------------------
    */
    'exchange_rate_api_key' => env('BILLING_EXCHANGE_RATE_API_KEY', null),
    'exchange_rates_key' => 'billing:exchange_rates',

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
