<?php

use App\Http\Controllers\VtuController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/vtu/webhooks/{provider:identifier}', [VtuController::class, 'webhook'])
    ->middleware(['throttle:120,1', 'ensure.addon:vtu.digital-services'])
    ->name('api.v1.vtu.webhook');

Route::middleware(['auth:sanctum','ensure.api.user','ensure.active.api','ensure.addon:vtu.digital-services','api.token:vtu.read'])
    ->prefix('/v1/vtu')->group(function (): void {
        Route::get('/services', [VtuController::class, 'apiServices'])->name('api.v1.vtu.services');
        Route::post('/quote', [VtuController::class, 'quote'])->middleware('throttle:120,1')->name('api.v1.vtu.quote');
        Route::get('/transactions', [VtuController::class, 'history'])->name('api.v1.vtu.transactions');
        Route::get('/transactions/{t}', [VtuController::class, 'show'])->name('api.v1.vtu.transaction');
        Route::post('/transactions/{t}/requery', [VtuController::class, 'requery'])->middleware('throttle:60,1')->name('api.v1.vtu.requery');
    });
Route::middleware(['auth:sanctum','ensure.api.user','ensure.active.api','ensure.addon:vtu.digital-services','api.token:vtu.transact','transaction.pin'])
    ->prefix('/v1/vtu')->group(function (): void {
        Route::post('/transactions', [VtuController::class, 'store'])->middleware('throttle:30,1')->name('api.v1.vtu.transactions.store');
    });
Route::middleware(['auth:sanctum','ensure.api.user','ensure.active.api','ensure.addon:vtu.digital-services','api.token:vtu.bulk','transaction.pin'])
    ->prefix('/v1/vtu')->group(function (): void {
        Route::post('/bulk', [VtuController::class, 'bulk'])->middleware('throttle:10,1')->name('api.v1.vtu.bulk');
    });
