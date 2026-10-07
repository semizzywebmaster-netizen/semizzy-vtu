<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Marketplace\Http\Controllers\MarketplaceController;

Route::middleware(['web','auth','ensure.addon:marketplace.commerce','permission:marketplace.view'])->group(function(){
 Route::get('/marketplace',[MarketplaceController::class,'index'])->name('marketplace.index');
});
Route::middleware(['web','auth','ensure.addon:marketplace.commerce','permission:marketplace.sell'])->group(function(){
 Route::post('/marketplace/products',[MarketplaceController::class,'productStore'])->name('marketplace.products.store');
 Route::patch('/marketplace/products/{product}',[MarketplaceController::class,'productUpdate'])->name('marketplace.products.update');
 Route::delete('/marketplace/products/{product}',[MarketplaceController::class,'productDestroy'])->name('marketplace.products.destroy');
});
