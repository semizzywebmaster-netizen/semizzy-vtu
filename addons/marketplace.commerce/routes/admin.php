<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Marketplace\Http\Controllers\MarketplaceController;
Route::middleware(['web','auth','ensure.addon:marketplace.commerce','permission:marketplace.manage'])->group(function(){
 Route::get('/admin/marketplace',[MarketplaceController::class,'admin'])->name('admin.marketplace.index');
 Route::post('/admin/marketplace/products',[MarketplaceController::class,'productStore'])->name('admin.marketplace.products.store');
 Route::patch('/admin/marketplace/products/{product}',[MarketplaceController::class,'productUpdate'])->name('admin.marketplace.products.update');
 Route::delete('/admin/marketplace/products/{product}',[MarketplaceController::class,'productDestroy'])->name('admin.marketplace.products.destroy');
 Route::post('/admin/marketplace/orders/{order}/refund',[MarketplaceController::class,'refund'])->name('admin.marketplace.orders.refund');
 Route::post('/admin/marketplace/orders/{order}/release-escrow',[MarketplaceController::class,'releaseEscrow'])->name('admin.marketplace.orders.release-escrow');
 Route::patch('/admin/marketplace/categories/{category}/profit',[MarketplaceController::class,'updateCategoryProfit'])->name('admin.marketplace.categories.profit');
});