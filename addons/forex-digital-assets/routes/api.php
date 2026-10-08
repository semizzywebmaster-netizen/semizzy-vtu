<?php

use Illuminate\Support\Facades\Route;
use Semizzy\Addons\ForexDigitalAssets\Http\Controllers\ForexMarketController;

Route::prefix('api/v1/forex-digital-assets')
    ->middleware(['api', 'ensure.addon:forex-digital-assets'])
    ->group(function (): void {
        Route::get('/instruments', [ForexMarketController::class, 'instruments'])
            ->name('api.forex-digital-assets.instruments');
        Route::get('/instruments/{instrument}/quotes', [ForexMarketController::class, 'quotes'])
            ->name('api.forex-digital-assets.quotes');
    });
