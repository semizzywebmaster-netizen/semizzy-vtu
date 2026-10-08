<?php

use Illuminate\Support\Facades\Route;
use Semizzy\Addons\ForexDigitalAssets\Http\Controllers\AdminForexDigitalAssetsController;

Route::prefix('admin/forex-digital-assets')
    ->middleware(['auth', 'verified', 'ensure.addon:forex-digital-assets', 'permission:forex.view'])
    ->group(function (): void {
        Route::get('/', [AdminForexDigitalAssetsController::class, 'index'])
            ->name('admin.forex-digital-assets.index');

        Route::post('/providers', [AdminForexDigitalAssetsController::class, 'storeProvider'])
            ->middleware('permission:forex.providers.manage')
            ->name('admin.forex-digital-assets.provider.store');
        Route::post('/providers/{provider}/verify', [AdminForexDigitalAssetsController::class, 'verifyProvider'])
            ->middleware('permission:forex.providers.manage')
            ->name('admin.forex-digital-assets.provider.verify');
        Route::post('/providers/{provider}/disable', [AdminForexDigitalAssetsController::class, 'disableProvider'])
            ->middleware('permission:forex.providers.manage')
            ->name('admin.forex-digital-assets.provider.disable');

        Route::post('/instruments', [AdminForexDigitalAssetsController::class, 'storeInstrument'])
            ->middleware('permission:forex.market.manage')
            ->name('admin.forex-digital-assets.instrument.store');
        Route::post('/instruments/{instrument}/verify', [AdminForexDigitalAssetsController::class, 'verifyInstrument'])
            ->middleware('permission:forex.market.manage')
            ->name('admin.forex-digital-assets.instrument.verify');
        Route::post('/instruments/{instrument}/publish', [AdminForexDigitalAssetsController::class, 'publishInstrument'])
            ->middleware('permission:forex.market.manage')
            ->name('admin.forex-digital-assets.instrument.publish');
        Route::post('/instruments/{instrument}/unpublish', [AdminForexDigitalAssetsController::class, 'unpublishInstrument'])
            ->middleware('permission:forex.market.manage')
            ->name('admin.forex-digital-assets.instrument.unpublish');
    });
