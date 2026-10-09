<?php

use Illuminate\\Support\\Facades\\Route;
use Semizzy\\Addons\\SchoolAdmission\\Http\\Controllers\\SchoolAdmissionController;

Route::middleware(['web','auth','ensure.addon:education.school-admission'])
    ->prefix('school-admission')
    ->group(function (): void {
        Route::get('/', [SchoolAdmissionController::class, 'index'])->middleware('permission:school-admission.view')->name('school-admission.index');
        Route::get('/transactions/{transaction}', [SchoolAdmissionController::class, 'show'])->middleware('permission:school-admission.view')->name('school-admission.transactions.show');
    });