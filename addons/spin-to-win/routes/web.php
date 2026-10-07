<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SpinToWinController;
Route::middleware(['auth','verified'])->group(function():void{
 Route::get('/spin-to-win',[SpinToWinController::class,'index'])->middleware('permission:spin.view')->name('spin.index');
 Route::post('/spin-to-win/{campaign}/spin',[SpinToWinController::class,'spin'])->whereNumber('campaign')->middleware('permission:spin.play')->name('spin.play');
});