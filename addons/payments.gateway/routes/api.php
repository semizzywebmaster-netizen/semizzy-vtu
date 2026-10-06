<?php

use IlluminateSupportFacadesRoute;
use AppHttpMiddlewareEnsureAddonActive;

Route::middleware(['auth:sanctum', 'ensure.addon:payments.gateway'])
    ->prefix('api/v1/payments')->group(function (): void {
        Route::post('/intents', [\Semizzy\Addons\Payments\Http\Controllers\PaymentController::class, 'create'])->middleware(['permission:payments.create', 'transaction.pin', 'throttle:10,1']);
        Route::get('/status/{reference}', [\Semizzy\Addons\Payments\Http\Controllers\PaymentController::class, 'status'])->middleware('permission:payments.view');
    });
