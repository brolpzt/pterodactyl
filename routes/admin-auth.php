<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Admin\Auth;

/*
|--------------------------------------------------------------------------
| Admin Authentication Routes
|--------------------------------------------------------------------------
|
| Endpoint: /admin/login
|
*/

Route::get('/login', [Auth\AdminLoginController::class, 'index'])->name('admin.login');
Route::get('/login/checkpoint', [Auth\AdminLoginController::class, 'checkpoint'])->name('admin.login.checkpoint');

Route::middleware(['throttle:authentication'])->group(function () {
    Route::post('/login', [Auth\AdminLoginController::class, 'login'])
        ->middleware('recaptcha')
        ->name('admin.login.submit');

    Route::post('/login/checkpoint', Auth\AdminLoginCheckpointController::class)
        ->name('admin.login-checkpoint');
});
