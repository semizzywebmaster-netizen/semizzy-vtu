<?php

use App\Http\Controllers\Admin\CacOrderController;
use App\Http\Controllers\Admin\CacServiceProductController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:ADMIN,STAFF,SUPPORT', 'verified', 'ensure.addon:cac.business-services'])
    ->prefix('admin')
    ->group(function (): void {
            Route::get('/cac/products', [\App\Http\Controllers\Admin\CacServiceProductController::class, 'index'])->middleware('permission:cac.products.manage')->name('admin.cac.products');
            Route::get('/cac/orders', [\App\Http\Controllers\Admin\CacOrderController::class, 'index'])->middleware('permission:cac.orders.manage')->name('admin.cac.orders');
            Route::get('/cac/orders/{order}', [\App\Http\Controllers\Admin\CacOrderController::class, 'show'])->middleware('permission:cac.orders.manage')->name('admin.cac.orders.show');
            Route::post('/cac/orders/{order}/review', [\App\Http\Controllers\Admin\CacOrderController::class, 'review'])->middleware('permission:cac.orders.manage')->name('admin.cac.orders.review');
            Route::post('/cac/orders/{order}/documents/{document}/review', [\App\Http\Controllers\Admin\CacOrderController::class, 'documentReview'])->middleware('permission:cac.documents.manage')->name('admin.cac.documents.review');
            Route::get('/cac/orders/{order}/documents/{document}', [\App\Http\Controllers\Admin\CacOrderController::class, 'downloadDocument'])->whereNumber('document')->middleware('permission:cac.documents.manage')->name('admin.cac.documents.show');
            Route::post('/cac/products', [\App\Http\Controllers\Admin\CacServiceProductController::class, 'store'])->middleware('permission:cac.products.manage')->name('admin.cac.products.store');
            Route::put('/cac/products/{product}', [\App\Http\Controllers\Admin\CacServiceProductController::class, 'update'])->middleware('permission:cac.products.manage')->name('admin.cac.products.update');
    
});
