<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Api\HgHlds\ActivationController;

/*
|--------------------------------------------------------------------------
| hgHLDS Engine API
|--------------------------------------------------------------------------
|
| Endpoint: /api/hghlds
|
*/
Route::post('/activate', ActivationController::class)->name('api:hghlds.activate');
