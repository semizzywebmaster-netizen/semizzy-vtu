<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\EnsureAddonActive;
use Semizzy\Addons\Payments\Http\Controllers\AdminPaymentsController;

Route::middleware(['auth', 'verified', 'role:ADMIN,STAFF,SUPPORT', EnsureAddonActive::class.':payments.gateway'])
    ->prefix('admin/payments')->name('admin.payments.')
    ->group(function (): void {
        Route::get('/', [AdminPaymentsController::class, 'index'])->middleware('permission:payments.view')->name('index');
    });
