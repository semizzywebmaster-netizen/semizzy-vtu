<?php

use Illuminate\Support\Facades\Route;
use Semizzy\Addons\SimHosting\Http\Controllers\SimHostingController;

Route::middleware(['auth','verified','ensure.addon:sim-hosting','permission:sim_hosting.view'])
    ->get('/sim-hosting', [SimHostingController::class, 'index']);