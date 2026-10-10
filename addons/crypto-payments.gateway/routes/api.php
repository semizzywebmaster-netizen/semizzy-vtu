<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\CryptoPayments\Http\Controllers\CryptoPaymentController;
use Semizzy\Addons\CryptoPayments\Http\Controllers\CryptoPaymentWebhookController;

Route::middleware(['auth:sanctum', 'ensure.addon:crypto-payments.gateway'])
    ->prefix('api/v1/crypto-payments')->group(function (): void {
        Route::post('/payments', [CryptoPaymentController::class, 'create'])
            ->middleware(['permission:crypto.create', 'transaction.pin', 'throttle:10,1']);
        Route::get('/payments/{reference}', [CryptoPaymentController::class, 'status'])
            ->middleware('permission:crypto.view');
    });

// Keep addon autoload failures from preventing Laravel from registering every route.
// The controller is resolved when this webhook endpoint is actually invoked.
Route::post('/api/v1/crypto-payments/webhooks/{provider}', function (Request $request, string $provider) {
    return app(CryptoPaymentWebhookController::class)($request, $provider);
})
    ->middleware('ensure.addon:crypto-payments.gateway')
    ->where('provider', '[A-Za-z0-9._-]+')
    ->name('crypto-payments.webhook');
