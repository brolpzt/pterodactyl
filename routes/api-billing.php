<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Api\Billing\StripeWebhookController;

Route::post('/stripe/webhook', StripeWebhookController::class);
