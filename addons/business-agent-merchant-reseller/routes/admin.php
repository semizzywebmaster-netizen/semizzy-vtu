<?php
use Illuminate\Support\Facades\Route;
use Addons\BusinessAgentMerchantReseller\Http\Controllers\BusinessAdminController;
Route::middleware(['auth','verified','ensure.addon:business.agent-merchant-reseller','permission:business.manage'])->group(function(){
 Route::get('/admin/business',[BusinessAdminController::class,'page']);
 Route::get('/admin/business/partners',[BusinessAdminController::class,'partners']);
 Route::post('/admin/business/partners/{partner}/approve',[BusinessAdminController::class,'approve'])->middleware('permission:business.approve');
 Route::post('/admin/business/partners/bulk-status',[BusinessAdminController::class,'bulkStatus'])->middleware('permission:business.approve');
 Route::patch('/admin/business/partners/{partner}',[BusinessAdminController::class,'update'])->middleware('permission:business.manage');
 Route::post('/admin/business/partners/bulk-commercial-settings',[BusinessAdminController::class,'bulkCommercialSettings'])->middleware('permission:business.manage');
 Route::post('/admin/business/partners/bulk-pricing',[BusinessAdminController::class,'bulkPricing'])->middleware('permission:business.pricing.manage');
 Route::post('/admin/business/partners/bulk-pricing-delete',[BusinessAdminController::class,'bulkDeletePricing'])->middleware('permission:business.pricing.manage');
 Route::get('/admin/business/partners/{partner}/pricing',[BusinessAdminController::class,'pricing'])->middleware('permission:business.pricing.manage');
 Route::post('/admin/business/partners/{partner}/pricing',[BusinessAdminController::class,'savePricing'])->middleware('permission:business.pricing.manage');
 Route::delete('/admin/business/partners/{partner}/pricing/{rule}',[BusinessAdminController::class,'deletePricing'])->middleware('permission:business.pricing.manage');
 Route::get('/admin/business/partners/{partner}/hierarchy',[BusinessAdminController::class,'hierarchy'])->middleware('permission:business.manage');
 Route::get('/admin/business/settlements',[BusinessAdminController::class,'settlements'])->middleware('permission:business.audit');
 Route::post('/admin/business/settlements/bulk-settle',[BusinessAdminController::class,'bulkSettle'])->middleware('permission:business.manage');
 Route::post('/admin/business/settlements/{settlement}/settle',[BusinessAdminController::class,'settle'])->middleware('permission:business.manage');
});
