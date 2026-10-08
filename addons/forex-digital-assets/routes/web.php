<?php

use Illuminate\Support\Facades\Route;
use Semizzy\Addons\ForexDigitalAssets\Http\Controllers\ForexMarketController;

Route::get('/forex-digital-assets', [ForexMarketController::class, 'index'])
    ->middleware(['web', 'ensure.addon:forex-digital-assets'])
    ->name('forex-digital-assets.market');
