<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Escrow\Http\Controllers\EscrowController;

Route::middleware(['web','auth','ensure.addon:escrow.protection'])->group(function(){
 Route::get('/escrow',[EscrowController::class,'index'])->middleware('permission:escrow.view')->name('escrow.index');
 Route::post('/escrow',[EscrowController::class,'store'])->middleware(['permission:escrow.create','transaction.pin'])->name('escrow.store');
 Route::post('/escrow/{escrow}/release',[EscrowController::class,'release'])->middleware(['permission:escrow.release','transaction.pin'])->name('escrow.release');
 Route::post('/escrow/{escrow}/cancel',[EscrowController::class,'cancel'])->middleware(['permission:escrow.create','transaction.pin'])->name('escrow.cancel');
 Route::post('/escrow/{escrow}/dispute',[EscrowController::class,'dispute'])->middleware(['permission:escrow.dispute','transaction.pin'])->name('escrow.dispute');

 Route::middleware(['permission:escrow.admin'])->group(function(){
  Route::get('/admin/escrow',[EscrowController::class,'admin'])->name('admin.escrow');
  Route::post('/admin/escrow/{escrow}/expire',[EscrowController::class,'expire'])->middleware('transaction.pin')->name('admin.escrow.expire');
  Route::post('/admin/escrow/{escrow}/resolve',[EscrowController::class,'resolve'])->middleware('transaction.pin')->name('admin.escrow.resolve');
 });
});