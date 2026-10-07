<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\P2p\Http\Controllers\P2pTransferController;
Route::middleware(['api','auth:sanctum','ensure.addon:p2p.transfers'])->group(function(){
 Route::post('/api/v1/p2p/transfers',[P2pTransferController::class,'apiStore'])->middleware(['permission:p2p.send','transaction.pin']);
});