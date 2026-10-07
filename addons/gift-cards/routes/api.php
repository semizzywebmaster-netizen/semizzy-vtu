<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\GiftCards\Http\Controllers\GiftCardsController;
Route::middleware('auth:sanctum')->prefix('api/v1/gift-cards')->group(function () {
 Route::get('/products',[GiftCardsController::class,'apiProducts'])->middleware('permission:giftcards.view');
 Route::post('/purchase',[GiftCardsController::class,'purchase'])->middleware(['permission:giftcards.buy','transaction.pin']);
 Route::get('/orders',[GiftCardsController::class,'apiOrders'])->middleware('permission:giftcards.view');
 Route::post('/orders/{order}/requery',[GiftCardsController::class,'requery'])->middleware('permission:giftcards.buy');
});