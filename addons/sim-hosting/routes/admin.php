<?php

use Illuminate\Support\Facades\Route;
use Semizzy\Addons\SimHosting\Http\Controllers\AdminSimHostingController;

Route::middleware(['auth','ensure.addon:sim-hosting'])
    ->prefix('admin/sim-hosting')->group(function () {
        Route::get('/', [AdminSimHostingController::class, 'index'])->middleware('permission:sim_hosting.view');
        Route::post('/products', [AdminSimHostingController::class, 'saveProduct'])->middleware('permission:sim_hosting.manage');
        Route::put('/products/{id}', [AdminSimHostingController::class, 'saveProduct'])->middleware('permission:sim_hosting.manage');
        Route::post('/products/{product}/toggle', [AdminSimHostingController::class, 'toggleProduct'])->middleware('permission:sim_hosting.manage');
        Route::post('/numbers', [AdminSimHostingController::class, 'saveNumber'])->middleware('permission:sim_hosting.manage');
        Route::put('/numbers/{id}', [AdminSimHostingController::class, 'saveNumber'])->middleware('permission:sim_hosting.manage');
        Route::post('/numbers/{number}/toggle', [AdminSimHostingController::class, 'toggleNumber'])->middleware('permission:sim_hosting.manage');
    });