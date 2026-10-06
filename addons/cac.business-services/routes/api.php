<?php

use App\Http\Controllers\Api\CacDocumentController;
use App\Http\Controllers\Api\CacOrderController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/cac/webhooks/{provider:identifier}', [CacWebhookController::class, 'handle'])
    ->middleware(['throttle:120,1', 'ensure.addon:cac.business-services'])
    ->name('api.v1.cac.webhook');

Route::middleware(['auth:sanctum','ensure.api.user','ensure.active.api','ensure.addon:cac.business-services','api.token:cac.read'])
    ->prefix('/v1/cac')->group(function (): void {
        Route::get('/products', [CacOrderController::class, 'products'])->name('api.v1.cac.products');
        Route::get('/orders/{order}', [CacOrderController::class, 'show'])->name('api.v1.cac.orders.show');
        Route::get('/orders/{order}/status-history', [CacOrderController::class, 'statusHistory'])->name('api.v1.cac.orders.status-history');
    });
Route::middleware(['auth:sanctum','ensure.api.user','ensure.active.api','ensure.addon:cac.business-services','api.token:cac.transact'])
    ->prefix('/v1/cac')->group(function (): void {
        Route::post('/orders/{order}/documents', [CacDocumentController::class, 'store'])->middleware('throttle:20,1')->name('api.v1.cac.documents.store');
        Route::delete('/orders/{order}/documents/{document}', [CacDocumentController::class, 'destroy'])->name('api.v1.cac.documents.destroy');
        Route::post('/orders', [CacOrderController::class, 'store'])->middleware('throttle:20,1')->name('api.v1.cac.orders.store');
    });
