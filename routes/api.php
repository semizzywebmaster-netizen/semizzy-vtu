<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Api\ApiTokenController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', fn () => response()->json([
    'status' => 'ok',
    'application' => config('app.name', 'SEMIZZY ONE'),
    'timestamp' => now()->toIso8601String(),
]))->name('api.v1.health');

Route::middleware(['auth:sanctum', 'ensure.active.api', 'api.token:core.read'])->get('/v1/me', fn (Request $request) => response()->json([
    'data' => $request->user(),
]))->name('api.v1.me');


Route::middleware(['auth', 'security.throttle:api.tokens,10,60'])->group(function (): void {
    Route::post('/v1/tokens', [ApiTokenController::class, 'store'])->name('api.v1.tokens.store');
    Route::get('/v1/tokens', [ApiTokenController::class, 'index'])->name('api.v1.tokens.index');
    Route::delete('/v1/tokens/{token}', [ApiTokenController::class, 'destroy'])->whereNumber('token')->name('api.v1.tokens.destroy');
    Route::delete('/v1/tokens', [ApiTokenController::class, 'revokeAll'])->name('api.v1.tokens.revoke-all');
});

Route::middleware(['auth:sanctum', 'ensure.active.api', 'api.token:core.read'])->get('/v1/core-check', fn () => response()->json(['status' => 'ok']))->name('api.v1.core-check');
