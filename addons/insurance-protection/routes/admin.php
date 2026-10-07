<?php
use Illuminate\Support\Facades\Route;
use Addons\InsuranceProtection\Http\Controllers\InsuranceAdminController;
Route::middleware(['auth','verified','ensure.addon:insurance.protection','permission:insurance.manage'])->group(function(){
 Route::get('/admin/insurance',[InsuranceAdminController::class,'page']);
 Route::get('/admin/insurance/providers',[InsuranceAdminController::class,'providers']);
 Route::post('/admin/insurance/providers',[InsuranceAdminController::class,'storeProvider'])->middleware('permission:insurance.providers.manage');
 Route::post('/admin/insurance/providers/{provider}/test',[InsuranceAdminController::class,'testProvider'])->middleware('permission:insurance.providers.manage');
 Route::patch('/admin/insurance/providers/{provider}',[InsuranceAdminController::class,'updateProvider'])->middleware('permission:insurance.providers.manage');
 Route::get('/admin/insurance/products',[InsuranceAdminController::class,'products']);
 Route::post('/admin/insurance/products',[InsuranceAdminController::class,'storeProduct'])->middleware('permission:insurance.manage');
 Route::post('/admin/insurance/products/{product}/toggle',[InsuranceAdminController::class,'toggleProduct'])->middleware('permission:insurance.manage');
 Route::get('/admin/insurance/claims',[InsuranceAdminController::class,'claims']);
 Route::patch('/admin/insurance/claims/{claim}',[InsuranceAdminController::class,'updateClaim'])->middleware('permission:insurance.claims.manage');
});