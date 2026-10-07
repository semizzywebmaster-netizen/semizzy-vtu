<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Marketplace\Http\Controllers\MarketplaceController;
Route::middleware(['web','auth','ensure.addon:marketplace.commerce','permission:marketplace.view'])->group(function(){
 Route::get('/marketplace',[MarketplaceController::class,'index'])->name('marketplace.index');
});