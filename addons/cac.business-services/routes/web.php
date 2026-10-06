<?php

use App\Http\Controllers\CacController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'ensure.addon:cac.business-services'])->group(function (): void {
    Route::get('/cac', [CacController::class, 'index'])->name('cac.index');
    Route::post('/cac/orders', [CacController::class, 'store'])->name('cac.orders.store');
    Route::get('/cac/orders', [CacController::class, 'orders'])->name('cac.orders');
    Route::get('/cac/orders/{order}', [CacController::class, 'show'])->name('cac.orders.show');
    Route::post('/cac/orders/{order}/documents', [CacController::class, 'uploadDocument'])->middleware('throttle:20,1')->name('cac.orders.documents.store');
    Route::delete('/cac/orders/{order}/documents/{document}', [CacController::class, 'deleteDocument'])->whereNumber('document')->middleware('throttle:20,1')->name('cac.orders.documents.destroy');
    Route::get('/cac/orders/{order}/documents/{document}', [CacController::class, 'downloadDocument'])->whereNumber('document')->name('cac.orders.documents.show');
});
