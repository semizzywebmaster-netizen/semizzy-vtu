<?php
use Illuminate\Support\Facades\Route;
use Addons\BusinessAgentMerchantReseller\Http\Controllers\BusinessAdminController;
Route::middleware(['auth','verified','ensure.addon:business.agent-merchant-reseller','permission:business.manage'])->group(function(){
 Route::get('/admin/business',[BusinessAdminController::class,'page']);
 Route::get('/admin/business/partners',[BusinessAdminController::class,'partners']);
 Route::post('/admin/business/partners/{partner}/approve',[BusinessAdminController::class,'approve'])->middleware('permission:business.approve');
 Route::patch('/admin/business/partners/{partner}',[BusinessAdminController::class,'update'])->middleware('permission:business.manage');
});
