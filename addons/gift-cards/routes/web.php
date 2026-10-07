<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\GiftCards\Http\Controllers\GiftCardsController;
Route::middleware(['auth','verified'])->group(function () {
 Route::get('/gift-cards',[GiftCardsController::class,'index'])->middleware('permission:giftcards.view');
 Route::post('/gift-cards/purchase',[GiftCardsController::class,'purchase'])->middleware(['permission:giftcards.buy','transaction.pin']);
 Route::get('/gift-cards/orders',[GiftCardsController::class,'orders'])->middleware('permission:giftcards.view');
});