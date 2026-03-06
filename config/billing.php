<?php

return [
    'manual_enabled' => env('BILLING_MANUAL_ENABLED', true),
    'stripe_enabled' => env('BILLING_STRIPE_ENABLED', false),
    'pix_enabled' => env('BILLING_PIX_ENABLED', false),
    'currency' => 'USD',
];
