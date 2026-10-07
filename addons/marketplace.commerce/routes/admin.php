<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Marketplace\Http\Controllers\MarketplaceController;
Route::middleware(['web','auth','ensure.addon:marketplace.commerce','permission:marketplace.manage'])->group(function(){
 Route::get('/admin/marketplace',[MarketplaceController::class,'admin'])->name('admin.marketplace.index');
});