<?php

use Illuminate\Support\Facades\Route;
use Semizzy\Addons\ForexDigitalAssets\Http\Controllers\AdminForexQuoteController;

Route::prefix('admin/forex-digital-assets/providers/{provider}/instruments/{instrument}')
    ->middleware(['auth', 'verified', 'ensure.addon:forex-digital-assets', 'permission:forex.market.manage'])
    ->group(function (): void {
        Route::post('/quote', [AdminForexQuoteController::class, 'store'])
            ->name('admin.forex-digital-assets.quote.store');
    });
