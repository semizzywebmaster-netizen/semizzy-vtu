<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Escrow\Http\Controllers\EscrowController;
Route::middleware(['web','auth','ensure.addon:escrow.protection','permission:escrow.manage'])->group(function(){
 Route::get('/admin/escrow',[EscrowController::class,'admin'])->name('admin.escrow');
 Route::post('/admin/escrow/{escrow}/resolve',[EscrowController::class,'resolve'])->middleware('permission:escrow.refund')->name('admin.escrow.resolve');
});