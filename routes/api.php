<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\VtuController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', fn () => response()->json([
    'status' => 'ok',
    'application' => config('app.name', 'SEMIZZY ONE'),
    'timestamp' => now()->toIso8601String(),
]))->name('api.v1.health');

Route::middleware(['auth:sanctum', 'ensure.api.user', 'ensure.active.api', 'api.token:core.read'])->get('/v1/me', fn (Request $request) => response()->json([
    'data' => $request->user()->only(['id', 'name', 'email', 'role', 'status', 'email_verified_at', 'created_at', 'updated_at']),
]))->name('api.v1.me');

Route::middleware(['auth', 'verified', 'ensure.api.user', 'ensure.active.api', 'security.throttle:api.tokens,10,60'])->group(function (): void {
    Route::post('/v1/tokens', [ApiTokenController::class, 'store'])->name('api.v1.tokens.store');
    Route::get('/v1/tokens', [ApiTokenController::class, 'index'])->name('api.v1.tokens.index');
    Route::delete('/v1/tokens/{token}', [ApiTokenController::class, 'destroy'])->whereNumber('token')->name('api.v1.tokens.destroy');
    Route::delete('/v1/tokens', [ApiTokenController::class, 'revokeAll'])->name('api.v1.tokens.revoke-all');
});

Route::middleware(['auth:sanctum', 'ensure.active.api', 'api.token:core.read'])->get('/v1/core-check', fn () => response()->json(['status' => 'ok']))->name('api.v1.core-check');

Route::post('/v1/vtu/webhooks/{provider:identifier}', [VtuController::class, 'webhook'])->middleware('throttle:120,1')->name('api.v1.vtu.webhook');

Route::middleware(['auth:sanctum','ensure.api.user','ensure.active.api','api.token:vtu.read','ensure.vtu'])->prefix('/v1/vtu')->group(function (): void {
    Route::get('/services', [VtuController::class, 'apiServices'])->name('api.v1.vtu.services');
    Route::post('/quote', [VtuController::class, 'quote'])->middleware('throttle:120,1')->name('api.v1.vtu.quote');
    Route::get('/transactions', [VtuController::class, 'history'])->name('api.v1.vtu.transactions');
    Route::get('/transactions/{t}', [VtuController::class, 'show'])->name('api.v1.vtu.transaction');
    Route::post('/transactions/{t}/requery', [VtuController::class, 'requery'])->middleware('throttle:60,1')->name('api.v1.vtu.requery');
});
Route::middleware(['auth:sanctum','ensure.api.user','ensure.active.api','api.token:vtu.transact','ensure.vtu','transaction.pin'])->prefix('/v1/vtu')->group(function (): void {
    Route::post('/transactions', [VtuController::class, 'store'])->middleware('throttle:30,1')->name('api.v1.vtu.transactions.store');
});
Route::middleware(['auth:sanctum','ensure.api.user','ensure.active.api','api.token:vtu.bulk','ensure.vtu','transaction.pin'])->prefix('/v1/vtu')->group(function (): void {
    Route::post('/bulk', [VtuController::class, 'bulk'])->middleware('throttle:10,1')->name('api.v1.vtu.bulk');
});
