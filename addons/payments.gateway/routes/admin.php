<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\EnsureAddonActive;
use Semizzy\Addons\Payments\Http\Controllers\AdminPaymentsController;

Route::middleware(['auth', 'verified', 'role:ADMIN,STAFF,SUPPORT', EnsureAddonActive::class.':payments.gateway'])
    ->prefix('admin/payments')->name('admin.payments.')
    ->group(function (): void {
        Route::get('/', [AdminPaymentsController::class, 'index'])->middleware('permission:payments.view')->name('index');

        Route::middleware('role:ADMIN')->group(function (): void {
            Route::post('/payments/{payment}/requery', [AdminPaymentsController::class, 'requeryPayment'])->middleware('permission:payments.view')->name('payments.requery');
            Route::post('/providers', [AdminPaymentsController::class, 'storeProvider'])->middleware('permission:payments.providers.manage')->name('providers.store');
            Route::put('/providers/{provider}', [AdminPaymentsController::class, 'updateProvider'])->middleware('permission:payments.providers.manage')->name('providers.update');
            Route::post('/providers/{provider}/test', [AdminPaymentsController::class, 'testProvider'])->middleware('permission:payments.providers.manage')->name('providers.test');
            Route::post('/providers/{provider}/state', [AdminPaymentsController::class, 'setState'])->middleware('permission:payments.providers.manage')->name('providers.state');
        });
    });
