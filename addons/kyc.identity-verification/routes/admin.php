<?php

use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Kyc\Http\Controllers\AdminKycController;

Route::middleware(['auth', 'role:ADMIN,STAFF,SUPPORT', 'verified', 'ensure.addon:kyc.identity-verification'])
    ->prefix('admin/kyc')->group(function () {
        Route::get('/', [AdminKycController::class, 'index'])->middleware('permission:kyc.view')->name('admin.kyc.index');
        Route::post('/{application}/review', [AdminKycController::class, 'review'])
            ->whereNumber('application')->middleware('permission:kyc.review')->name('admin.kyc.review');
        Route::get('/documents/{document}', [AdminKycController::class, 'document'])
            ->whereNumber('document')->middleware('permission:kyc.documents.manage')->name('admin.kyc.document');
    });
