<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Api\Billing\SicoobPixWebhookController;
use Pterodactyl\Http\Controllers\Api\Billing\StripeWebhookController;

Route::post('/stripe/webhook', StripeWebhookController::class);
Route::post('/pix/sicoob/webhook', SicoobPixWebhookController::class);
