<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\EnsureAddonActive;
use Semizzy\Addons\CryptoPayments\Http\Controllers\CryptoPaymentController;
use Semizzy\Addons\CryptoPayments\Http\Controllers\CryptoPaymentWebhookController;

Route::middleware(['auth:sanctum', 'ensure.addon:crypto-payments.gateway'])
    ->prefix('api/v1/crypto-payments')->group(function (): void {
        Route::post('/payments', [CryptoPaymentController::class, 'create'])
            ->middleware(['permission:crypto.create', 'transaction.pin', 'throttle:10,1']);
        Route::get('/payments/{reference}', [CryptoPaymentController::class, 'status'])
            ->middleware('permission:crypto.view');
    });

Route::post('/api/v1/crypto-payments/webhooks/{provider}', CryptoPaymentWebhookController::class)
    ->middleware('ensure.addon:crypto-payments.gateway')
    ->where('provider', '[A-Za-z0-9._-]+')
    ->name('crypto-payments.webhook');
