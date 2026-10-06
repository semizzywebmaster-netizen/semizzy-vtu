<?php

use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Payments\Http\Controllers\PaymentController;
use Semizzy\Addons\Payments\Http\Controllers\PaymentWebhookController;

Route::middleware(['auth:sanctum', 'ensure.addon:payments.gateway'])
    ->prefix('api/v1/payments')->group(function (): void {
        Route::post('/intents', [PaymentController::class, 'create'])->middleware(['permission:payments.create', 'transaction.pin', 'throttle:10,1']);
        Route::get('/status/{reference}', [PaymentController::class, 'status'])->middleware('permission:payments.view');
    });

Route::post('/api/v1/payments/webhooks/{provider}', PaymentWebhookController::class)
    ->middleware('ensure.addon:payments.gateway')
    ->where('provider', '[A-Za-z0-9._-]+')
    ->name('payments.webhook');
