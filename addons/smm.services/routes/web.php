<?php
use App\Http\Controllers\SmmController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web','auth','verified','ensure.addon:smm.services'])->group(function(){
 Route::get('/smm',[SmmController::class,'index'])->middleware('permission:smm.view')->name('smm.index');
 Route::post('/smm/orders',[SmmController::class,'store'])->middleware(['permission:smm.orders.manage','throttle:20,1','transaction.pin'])->name('smm.orders.store');
 Route::get('/smm/orders',[SmmController::class,'history'])->middleware('permission:smm.view')->name('smm.orders.history');
 Route::post('/smm/orders/{order}/requery',[SmmController::class,'requery'])->middleware(['permission:smm.requery','throttle:20,1'])->name('smm.orders.requery');
 Route::post('/smm/orders/{order}/cancel',[SmmController::class,'cancel'])->middleware(['permission:smm.cancel','throttle:10,1','transaction.pin'])->name('smm.orders.cancel');
});