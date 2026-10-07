<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\GiftCards\Http\Controllers\Admin\GiftCardsController;
Route::prefix('admin/gift-cards')->middleware(['auth','verified','role:ADMIN|STAFF|SUPPORT'])->group(function () {
 Route::get('/',[GiftCardsController::class,'index'])->middleware('permission:giftcards.view');
 Route::post('/products',[GiftCardsController::class,'storeProduct'])->middleware('permission:giftcards.products.manage');
 Route::post('/products/{product}/toggle',[GiftCardsController::class,'toggleProduct'])->middleware('permission:giftcards.products.manage');
 Route::post('/orders/{order}/requery',[GiftCardsController::class,'requery'])->middleware('permission:giftcards.manage');
 Route::post('/orders/{order}/refund',[GiftCardsController::class,'requestRefund'])->middleware('permission:giftcards.refunds.manage');
 Route::post('/refunds/{refund}/approve',[GiftCardsController::class,'approveRefund'])->middleware('permission:giftcards.refunds.manage');
});