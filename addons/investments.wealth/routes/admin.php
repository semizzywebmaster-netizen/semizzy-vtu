<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Investments\Http\Controllers\AdminInvestmentsController;
Route::prefix('admin/investments')->middleware(['auth','ensure.addon:investments.wealth','permission:investments.view'])->group(function(){Route::get('/',[AdminInvestmentsController::class,'index'])->name('admin.investments.index');Route::post('/products/{product}/toggle',[AdminInvestmentsController::class,'toggle'])->middleware('permission:investments.manage')->name('admin.investments.product.toggle');});