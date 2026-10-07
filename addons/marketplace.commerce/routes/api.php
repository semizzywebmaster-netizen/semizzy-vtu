<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Marketplace\Http\Controllers\MarketplaceController;
Route::middleware(['api','auth:sanctum','ensure.addon:marketplace.commerce','permission:marketplace.buy','transaction.pin'])->group(function(){
 Route::post('/api/v1/marketplace/orders',[MarketplaceController::class,'store'])->name('api.marketplace.orders.store');
});