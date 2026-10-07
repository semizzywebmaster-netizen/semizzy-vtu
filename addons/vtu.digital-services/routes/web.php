<?php

use App\Http\Controllers\VtuController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'ensure.addon:vtu.digital-services'])->group(function (): void {
    Route::get('/vtu', [VtuController::class, 'index'])->middleware('permission:vtu.view')->name('vtu.services');
    Route::post('/vtu/quote', [VtuController::class, 'quote'])->middleware('throttle:120,1')->name('vtu.quote');
    Route::post('/vtu/purchase', [VtuController::class, 'store'])->middleware(['throttle:30,1', 'transaction.pin'])->name('vtu.purchase');
    Route::post('/vtu/bulk', [VtuController::class, 'bulk'])->middleware(['throttle:10,1', 'transaction.pin'])->name('vtu.bulk');
});
