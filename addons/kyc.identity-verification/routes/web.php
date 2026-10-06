<?php

use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Kyc\Http\Controllers\KycController;

Route::middleware(['auth', 'verified', 'ensure.addon:kyc.identity-verification'])
    ->group(function () {
        Route::get('/kyc', [KycController::class, 'index'])->name('kyc.index');
        Route::post('/kyc/otp', [KycController::class, 'sendOtp'])
            ->middleware(['permission:kyc.verify', 'throttle:3,1'])
            ->name('kyc.otp.send');
        Route::post('/kyc/otp/verify', [KycController::class, 'verifyOtp'])
            ->middleware(['permission:kyc.verify', 'throttle:10,1'])
            ->name('kyc.otp.verify');
        Route::post('/kyc', [KycController::class, 'submit'])
            ->middleware(['permission:kyc.submit', 'throttle:5,1', 'transaction.pin'])
            ->name('kyc.submit');
        Route::get('/kyc/document/{document}', [KycController::class, 'document'])
            ->whereNumber('document')->middleware('permission:kyc.submit')
            ->name('kyc.document');
    });
