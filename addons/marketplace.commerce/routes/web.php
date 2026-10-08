<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Marketplace\Http\Controllers\MarketplaceController;

Route::middleware(['web','auth','ensure.addon:marketplace.commerce','permission:marketplace.view'])->group(function(){
 Route::get('/marketplace',[MarketplaceController::class,'index'])->name('marketplace.index');
 Route::get('/marketplace/categories',[MarketplaceController::class,'categories'])->name('marketplace.categories');
 Route::get('/marketplace/sell',[MarketplaceController::class,'sell'])->name('marketplace.sell');
 Route::get('/marketplace/categories/{category}/form',[MarketplaceController::class,'categoryForm'])->name('marketplace.categories.form');
 Route::get('/marketplace/seller/categories',[MarketplaceController::class,'sellerCategories'])->name('marketplace.seller.categories');
});
Route::middleware(['web','auth','ensure.addon:marketplace.commerce','permission:marketplace.sell'])->group(function(){
 Route::post('/marketplace/products',[MarketplaceController::class,'productStore'])->name('marketplace.products.store');
 Route::patch('/marketplace/products/{product}',[MarketplaceController::class,'productUpdate'])->name('marketplace.products.update');
 Route::delete('/marketplace/products/{product}',[MarketplaceController::class,'productDestroy'])->name('marketplace.products.destroy');
 Route::post('/marketplace/products/{product}/media',[MarketplaceController::class,'storeMedia'])->name('marketplace.products.media.store');
 Route::post('/marketplace/products/{product}/media/{media}/primary',[MarketplaceController::class,'setPrimaryMedia'])->name('marketplace.products.media.primary');
 Route::delete('/marketplace/products/{product}/media/{media}',[MarketplaceController::class,'deleteMedia'])->name('marketplace.products.media.destroy');
});
