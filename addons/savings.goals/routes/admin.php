<?php
use Illuminate\\Support\\Facades\\Route;
Route::middleware(['auth','ensure.addon:savings.goals','permission:savings.manage'])->group(function(){ Route::get('/admin/savings', [\\Semizzy\\Addons\\Savings\\Http\\Controllers\\AdminSavingsController::class,'index']); });