<?php

use IlluminateSupportFacadesRoute;
use AppHttpMiddlewareEnsureAddonActive;

Route::middleware(['auth:sanctum', 'ensure.addon:payments.gateway'])
    ->prefix('api/v1/payments')->group(function (): void {
        Route::get('/status/{reference}', fn (string $reference) => response()->json(['reference' => $reference]))->middleware('permission:payments.view');
    });
