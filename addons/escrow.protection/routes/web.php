<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Escrow\Http\Controllers\EscrowController;
Route::middleware(['web','auth','ensure.addon:escrow.protection'])->group(function(){
 Route::get('/escrow',[EscrowController::class,'index'])->middleware('permission:escrow.view')->name('escrow.index');
 Route::post('/escrow',[EscrowController::class,'store'])->middleware(['permission:escrow.create','transaction.pin'])->name('escrow.store');
});