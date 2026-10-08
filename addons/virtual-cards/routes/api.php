<?php
use Illuminate\Support\Facades\Route;
use Addons\VirtualCards\Http\Controllers\VirtualCardController;

Route::middleware('auth')->group(function () {
 Route::get('/virtual-cards',[VirtualCardController::class,'index'])->middleware('permission:virtual-cards.view');
 Route::post('/virtual-cards/request',[VirtualCardController::class,'request'])->middleware('permission:virtual-cards.manage');
 Route::post('/virtual-cards/{card}/limit',[VirtualCardController::class,'limit'])->middleware('permission:virtual-cards.manage');
 Route::post('/virtual-cards/{card}/freeze',[VirtualCardController::class,'freeze'])->middleware('permission:virtual-cards.freeze');
 Route::post('/virtual-cards/{card}/unfreeze',[VirtualCardController::class,'unfreeze'])->middleware('permission:virtual-cards.freeze');
});
