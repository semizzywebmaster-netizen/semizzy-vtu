<?php
use Illuminate\Support\Facades\Route;
use Addons\InsuranceProtection\Http\Controllers\InsuranceController;
Route::middleware(['auth','verified','ensure.addon:insurance.protection'])->group(function(){
 Route::get('/insurance',[InsuranceController::class,'index'])->middleware('permission:insurance.view');
 Route::get('/insurance/products',[InsuranceController::class,'products'])->middleware('permission:insurance.view');
 Route::get('/insurance/policies',[InsuranceController::class,'policies'])->middleware('permission:insurance.view');
 Route::post('/insurance/policies/{policy}/requery',[InsuranceController::class,'requery'])->middleware('permission:insurance.view');
 Route::post('/insurance/policies/{policy}/cancel',[InsuranceController::class,'cancel'])->middleware('permission:insurance.policies.manage');
 Route::post('/insurance/policies/{policy}/renew',[InsuranceController::class,'renew'])->middleware('permission:insurance.buy');
 Route::get('/insurance/claims',[InsuranceController::class,'claims'])->middleware('permission:insurance.view');
 Route::post('/insurance/purchase',[InsuranceController::class,'purchase'])->middleware('permission:insurance.buy');
 Route::post('/insurance/policies/{policy}/claims',[InsuranceController::class,'claim'])->middleware('permission:insurance.buy');
});