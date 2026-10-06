<?php

use Illuminate\Support\Facades\Route;
use Semizzy\Addons\SimHosting\Http\Controllers\AdminSimHostingController;

Route::middleware(['auth','ensure.addon:sim-hosting','permission:sim_hosting.view'])
    ->prefix('admin/sim-hosting')->group(function () {
        Route::get('/', [AdminSimHostingController::class, 'index']);
    });