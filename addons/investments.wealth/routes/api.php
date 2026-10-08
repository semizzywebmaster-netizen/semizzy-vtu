<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Investments\Http\Controllers\InvestmentController;
Route::prefix('api/v1/investments')->middleware(['auth:sanctum','ensure.addon:investments.wealth'])->group(function(){
 Route::get('/',[InvestmentController::class,'apiIndex'])->middleware('permission:investments.view');
 Route::get('/market',[InvestmentController::class,'marketApi'])->middleware('permission:investments.market.view');
 Route::post('/applications',[InvestmentController::class,'create'])->middleware(['permission:investments.create','transaction.pin','throttle:10,1']);
 Route::post('/{reference}/fund',[InvestmentController::class,'fund'])->middleware(['permission:investments.create','transaction.pin','throttle:10,1']);
 Route::post('/{reference}/redeem',[InvestmentController::class,'redeem'])->middleware(['permission:investments.redeem','transaction.pin','throttle:5,1']);
});