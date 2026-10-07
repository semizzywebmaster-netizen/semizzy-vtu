<?php
use Illuminate\Support\Facades\Route;
use Addons\BusinessAgentMerchantReseller\Http\Controllers\BusinessController;
Route::middleware(['auth','verified','ensure.addon:business.agent-merchant-reseller'])->group(function(){
 Route::get('/business',[BusinessController::class,'index'])->middleware('permission:business.view');
 Route::get('/business/partners',[BusinessController::class,'partners'])->middleware('permission:business.view');
 Route::post('/business/apply',[BusinessController::class,'apply'])->middleware('permission:business.view');
});
