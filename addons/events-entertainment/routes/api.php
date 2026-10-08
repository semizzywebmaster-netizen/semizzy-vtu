<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum','ensure.addon:events.entertainment'])
    ->prefix('api/v1/events')
    ->group(function () {
        Route::get('/health', fn () => response()->json(['ok' => true]))
            ->middleware('permission:events.view');
    });
