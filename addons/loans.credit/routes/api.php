<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Loans\Http\Controllers\LoanController;
Route::prefix('api/v1/loans')->middleware(['auth:sanctum','ensure.addon:loans.credit'])->group(function(){Route::get('/',[LoanController::class,'apiIndex'])->middleware('permission:loans.view');Route::post('/applications',[LoanController::class,'apply'])->middleware(['permission:loans.apply','transaction.pin','throttle:10,1']);Route::post('/{reference}/repay',[LoanController::class,'repay'])->middleware(['permission:loans.repay','transaction.pin','throttle:10,1']);});