<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\SpinToWin\Http\Controllers\Admin\SpinToWinController;
Route::prefix('admin/spin-to-win')->middleware(['auth','verified','role:ADMIN,STAFF,SUPPORT'])->group(function():void{
 Route::get('/',[SpinToWinController::class,'index'])->middleware('permission:spin.manage')->name('admin.spin.index');
 Route::post('/campaigns',[SpinToWinController::class,'storeCampaign'])->middleware('permission:spin.manage');
 Route::put('/campaigns/{campaign}',[SpinToWinController::class,'updateCampaign'])->whereNumber('campaign')->middleware('permission:spin.manage');
 Route::post('/campaigns/{campaign}/toggle',[SpinToWinController::class,'toggleCampaign'])->whereNumber('campaign')->middleware('permission:spin.manage');
 Route::post('/campaigns/{campaign}/prizes',[SpinToWinController::class,'storePrize'])->whereNumber('campaign')->middleware('permission:spin.manage');
 Route::put('/prizes/{prize}',[SpinToWinController::class,'updatePrize'])->whereNumber('prize')->middleware('permission:spin.manage');
 Route::get('/plays',[SpinToWinController::class,'plays'])->middleware('permission:spin.view')->name('admin.spin.plays');
});