<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Api\Boost\BoostController;

/*
|--------------------------------------------------------------------------
| Server Boost API
|--------------------------------------------------------------------------
|
| Endpoint: /api/boost/{token}
|
| Used by the external CS 1.6 boost system; the token in the URL is the only
| credential (Admin → Server Boost).
|
*/
Route::get('/{token}/servers', [BoostController::class, 'servers'])->name('api:boost.servers');
Route::match(['get', 'post'], '/{token}/connect', [BoostController::class, 'connect'])->name('api:boost.connect');
Route::get('/{token}/blacklist', [BoostController::class, 'blacklist'])->name('api:boost.blacklist');
