<?php

use Illuminate\Support\Facades\Route;
use Semizzy\Addons\CryptoPayments\Http\Controllers\CryptoPaymentProviderAdminController;
use Semizzy\Addons\CryptoPayments\Http\Controllers\CryptoFundingSettingsAdminController;

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


Route::middleware(['auth', 'verified', 'permission:crypto.settings.manage'])
    ->prefix('admin/crypto-payments/settings')
    ->name('admin.crypto-payments.settings.')
    ->group(function (): void {
        Route::get('/funding-fee', [CryptoFundingSettingsAdminController::class, 'show'])->name('funding-fee.show');
        Route::patch('/funding-fee', [CryptoFundingSettingsAdminController::class, 'update'])->name('funding-fee.update');
    });
