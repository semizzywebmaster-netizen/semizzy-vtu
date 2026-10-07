<?php
use Illuminate\Support\Facades\Route;
use Addons\InsuranceProtection\Http\Controllers\InsuranceController;
Route::middleware(['auth','verified','ensure.addon:insurance.protection'])->prefix('api/insurance')->group(function(){
 Route::get('/products',[InsuranceController::class,'products']);
 Route::get('/policies',[InsuranceController::class,'policies']);
 Route::get('/claims',[InsuranceController::class,'claims']);
});