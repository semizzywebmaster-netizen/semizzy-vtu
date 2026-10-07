<?php

use Illuminate\Support\Facades\Route;
use Semizzy\Addons\TravelTickets\Http\Controllers\Admin\TravelTicketsController;

Route::prefix('admin/travel-tickets')
    ->middleware(['auth', 'verified', 'role:ADMIN,STAFF,SUPPORT'])
    ->group(function (): void {
        Route::get('/', [TravelTicketsController::class, 'index'])
            ->middleware('permission:travel.view')
            ->name('admin.travel.index');

        Route::post('/services', [TravelTicketsController::class, 'storeService'])
            ->middleware('permission:travel.services.manage')
            ->name('admin.travel.services.store');

        Route::post('/services/{service}/toggle', [TravelTicketsController::class, 'toggleService'])
            ->whereNumber('service')
            ->middleware('permission:travel.services.manage')
            ->name('admin.travel.services.toggle');

        Route::post('/bookings/{booking}/refund', [TravelTicketsController::class, 'requestRefund'])
            ->whereNumber('booking')
            ->middleware('permission:travel.refunds.manage')
            ->name('admin.travel.refunds.request');

        Route::post('/refunds/{refund}/approve', [TravelTicketsController::class, 'approveRefund'])
            ->whereNumber('refund')
            ->middleware('permission:travel.refunds.manage')
            ->name('admin.travel.refunds.approve');
    });