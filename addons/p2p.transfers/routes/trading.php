<?php

use Illuminate\Support\Facades\Route;
use Semizzy\Addons\P2p\Http\Controllers\P2pTradingController;

Route::middleware(['web','auth','ensure.addon:p2p.transfers'])->group(function () {
    Route::get('/p2p/trading', [P2pTradingController::class, 'index'])->middleware('permission:p2p.view')->name('p2p.trading');
    Route::post('/p2p/trading/listings', [P2pTradingController::class, 'createListing'])->middleware(['permission:p2p.listings.manage','transaction.pin','throttle:20,1'])->name('p2p.trading.listings.create');
    Route::post('/p2p/trading/offers', [P2pTradingController::class, 'offer'])->middleware(['permission:p2p.offers.manage','transaction.pin','throttle:20,1'])->name('p2p.trading.offers.create');
    Route::post('/p2p/trading/offers/{offer}/accept', [P2pTradingController::class, 'accept'])->middleware(['permission:p2p.offers.manage','transaction.pin','throttle:10,1'])->name('p2p.trading.offers.accept');
    Route::post('/p2p/trading/offers/{offer}/reject', [P2pTradingController::class, 'reject'])->middleware(['permission:p2p.offers.manage','transaction.pin','throttle:20,1'])->name('p2p.trading.offers.reject');
    Route::post('/p2p/trading/offers/{offer}/cancel', [P2pTradingController::class, 'cancel'])->middleware(['permission:p2p.offers.manage','transaction.pin','throttle:20,1'])->name('p2p.trading.offers.cancel');
    Route::post('/p2p/trading/offers/{offer}/requery', [P2pTradingController::class, 'requery'])->middleware(['permission:p2p.view','transaction.pin','throttle:10,1'])->name('p2p.trading.offers.requery');
});
