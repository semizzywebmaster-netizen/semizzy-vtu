<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\P2p\Http\Controllers\P2pTransferController;
Route::middleware(['web','auth','ensure.addon:p2p.transfers'])->group(function(){
 Route::get('/p2p/transfers',[P2pTransferController::class,'index'])->middleware('permission:p2p.view')->name('p2p.transfers');
 Route::post('/p2p/transfers',[P2pTransferController::class,'store'])->middleware(['permission:p2p.send','transaction.pin'])->name('p2p.transfers.store');
});
require __DIR__.'/trading.php';
