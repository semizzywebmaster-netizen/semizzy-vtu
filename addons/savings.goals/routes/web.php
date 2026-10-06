<?php
use Illuminate\Support\Facades\Route;
Route::middleware(['auth','verified','ensure.addon:savings.goals'])->group(function(){ Route::get('/savings', [\Semizzy\Addons\Savings\Http\Controllers\SavingsController::class,'index'])->middleware('permission:savings.view'); });