<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Escrow\Http\Controllers\EscrowController;
Route::middleware(['api','auth:sanctum','ensure.addon:escrow.protection','transaction.pin'])->group(function(){
 Route::post('/api/v1/escrow',[EscrowController::class,'store'])->middleware('permission:escrow.create');
 Route::post('/api/v1/escrow/{escrow}/release',[EscrowController::class,'release'])->middleware('permission:escrow.release');
 Route::post('/api/v1/escrow/{escrow}/cancel',[EscrowController::class,'cancel'])->middleware('permission:escrow.create');
 Route::post('/api/v1/escrow/{escrow}/dispute',[EscrowController::class,'dispute'])->middleware('permission:escrow.dispute');
 Route::post('/api/v1/escrow/{escrow}/resolve',[EscrowController::class,'resolve'])->middleware('permission:escrow.refund');
});