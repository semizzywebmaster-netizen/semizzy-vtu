<?php

use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Route;

Route::get('/v1/health', fn () => response()->json([
    'status' => 'ok',
    'application' => config('app.name', 'SEMIZZY ONE'),
    'timestamp' => now()->toIso8601String(),
]))->name('api.v1.health');

Route::middleware('auth:sanctum')->get('/v1/me', fn (Request $request) => response()->json([
    'data' => $request->user(),
]))->name('api.v1.me');
