<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Loans\Http\Controllers\LoanController;
Route::middleware(['auth','verified','ensure.addon:loans.credit'])->group(function(){Route::get('/loans',[LoanController::class,'index'])->middleware('permission:loans.view');});