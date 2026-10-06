<?php

use Illuminate\Support\Facades\Route;
use Semizzy\Addons\SimHosting\Http\Controllers\SimHostingController;

Route::middleware(['auth:sanctum','ensure.addon:sim-hosting'])->prefix('api/v1/sim-hosting')->group(function () {
    Route::get('/', [SimHostingController::class,'apiIndex'])->middleware('permission:sim_hosting.view');
    Route::post('/rentals', [SimHostingController::class,'rent'])->middleware(['permission:sim_hosting.rent','transaction.pin','throttle:10,1']);
    Route::post('/rentals/{reference}/renew', [SimHostingController::class,'renew'])->middleware(['permission:sim_hosting.rent','transaction.pin','throttle:10,1']);
});