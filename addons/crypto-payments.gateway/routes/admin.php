<?php

use Illuminate\Support\Facades\Route;
use Semizzy\Addons\CryptoPayments\Http\Controllers\CryptoPaymentProviderAdminController;

Route::middleware(['auth', 'verified', 'permission:crypto.providers.manage'])
    ->prefix('admin/crypto-payments/providers')
    ->name('admin.crypto-payments.providers.')
    ->group(function (): void {
        Route::get('/', [CryptoPaymentProviderAdminController::class, 'index'])->name('index');
        Route::post('/', [CryptoPaymentProviderAdminController::class, 'store'])->name('store');
        Route::patch('/{provider}', [CryptoPaymentProviderAdminController::class, 'update'])->name('update');
        Route::post('/{provider}/toggle', [CryptoPaymentProviderAdminController::class, 'toggle'])->name('toggle');
        Route::post('/{provider}/test', [CryptoPaymentProviderAdminController::class, 'test'])->name('test');
    });
