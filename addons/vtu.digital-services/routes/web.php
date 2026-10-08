<?php

use App\Http\Controllers\VtuController;
use App\Http\Controllers\VtuConversionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'ensure.addon:vtu.digital-services'])->group(function (): void {
    Route::get('/vtu', [VtuController::class, 'index'])->middleware('permission:vtu.view')->name('vtu.services');
    Route::get('/vtu/conversions', [VtuConversionController::class, 'index'])->middleware('permission:vtu.view')->name('vtu.conversions');
    Route::post('/vtu/conversions', [VtuConversionController::class, 'store'])->middleware(['permission:vtu.view','throttle:10,1','transaction.pin'])->name('vtu.conversions.store');
    Route::get('/vtu/conversions/{conversion}', [VtuConversionController::class, 'show'])->middleware('permission:vtu.view')->name('vtu.conversions.show');
    Route::post('/vtu/quote', [VtuController::class, 'quote'])->middleware('throttle:120,1')->name('vtu.quote');
    Route::post('/vtu/purchase', [VtuController::class, 'store'])->middleware(['throttle:30,1', 'transaction.pin'])->name('vtu.purchase');
    Route::post('/vtu/network-lookup', [VtuController::class, 'networkLookup'])->middleware('throttle:30,1')->name('vtu.network-lookup');
    Route::post('/vtu/bulk-quote', [VtuController::class, 'bulkQuote'])->middleware('throttle:60,1')->name('vtu.bulk-quote');
    Route::post('/vtu/bulk', [VtuController::class, 'bulk'])->middleware(['throttle:10,1', 'transaction.pin'])->name('vtu.bulk');
    Route::post('/vtu/bulk/{bulk}/items/{item}/requery', [VtuController::class, 'bulkItemRequery'])->middleware('throttle:20,1')->name('vtu.bulk.item-requery');
});