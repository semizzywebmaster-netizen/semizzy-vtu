<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Marketplace\Http\Controllers\MarketplaceController;

Route::middleware(['api','auth:sanctum','ensure.addon:marketplace.commerce','permission:marketplace.buy','transaction.pin'])->group(function(){
 Route::post('/api/v1/marketplace/orders',[MarketplaceController::class,'store'])->name('api.marketplace.orders.store');
 Route::post('/api/v1/marketplace/orders/{order}/pay',[MarketplaceController::class,'pay'])->name('api.marketplace.orders.pay');
 Route::post('/api/v1/marketplace/orders/{order}/refund',[MarketplaceController::class,'refund'])->name('api.marketplace.orders.refund');
 Route::post('/api/v1/marketplace/orders/{order}/review',[MarketplaceController::class,'reviewStore'])->name('api.marketplace.orders.review');
});
