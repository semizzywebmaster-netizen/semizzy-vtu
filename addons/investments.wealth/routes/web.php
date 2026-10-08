<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Investments\Http\Controllers\InvestmentController;
Route::middleware(['auth','verified','ensure.addon:investments.wealth','permission:investments.view'])->group(function(){
 Route::get('/investments',[InvestmentController::class,'index'])->name('investments.index');
 Route::get('/investments/market',[InvestmentController::class,'market'])->name('investments.market');
});