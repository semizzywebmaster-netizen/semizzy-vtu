<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\P2p\Http\Controllers\P2pTransferController;
Route::middleware(['api','auth:sanctum','ensure.addon:p2p.transfers'])->group(function(){
 Route::post('/api/v1/p2p/transfers',[P2pTransferController::class,'apiStore'])->middleware(['permission:p2p.send','transaction.pin']);
});

use Semizzy\Addons\P2p\Http\Controllers\P2pTradingController;
Route::middleware(['api','auth:sanctum','ensure.addon:p2p.transfers'])->group(function () {
    Route::get('/api/v1/p2p/trading', [P2pTradingController::class, 'index'])->middleware('permission:p2p.view');
    Route::post('/api/v1/p2p/trading/listings', [P2pTradingController::class, 'createListing'])->middleware(['permission:p2p.listings.manage','transaction.pin']);
    Route::post('/api/v1/p2p/trading/offers', [P2pTradingController::class, 'offer'])->middleware(['permission:p2p.offers.manage','transaction.pin']);
    Route::post('/api/v1/p2p/trading/offers/{offer}/accept', [P2pTradingController::class, 'accept'])->middleware(['permission:p2p.offers.manage','transaction.pin']);
    Route::post('/api/v1/p2p/trading/offers/{offer}/reject', [P2pTradingController::class, 'reject'])->middleware(['permission:p2p.offers.manage','transaction.pin']);
    Route::post('/api/v1/p2p/trading/offers/{offer}/cancel', [P2pTradingController::class, 'cancel'])->middleware(['permission:p2p.offers.manage','transaction.pin']);
    Route::post('/api/v1/p2p/trading/offers/{offer}/requery', [P2pTradingController::class, 'requery'])->middleware(['permission:p2p.view','transaction.pin','throttle:10,1']);
});
