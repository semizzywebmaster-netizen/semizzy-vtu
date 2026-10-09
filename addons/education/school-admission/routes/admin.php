<?php

use Illuminate\Support\Facades\Route;
use Semizzy\Addons\SchoolAdmission\Http\Controllers\AdminSchoolAdmissionController;

Route::middleware(['web','auth','role:ADMIN,STAFF,SUPPORT','ensure.addon:education.school-admission'])
    ->prefix('admin/school-admission')
    ->group(function (): void {
        Route::get('/', [AdminSchoolAdmissionController::class, 'index'])->middleware('permission:school-admission.view')->name('admin.school-admission.index');
        Route::post('/institutions', [AdminSchoolAdmissionController::class, 'storeInstitution'])->middleware('permission:school-admission.institutions.manage')->name('admin.school-admission.institutions.store');
        Route::put('/institutions/{institution}', [AdminSchoolAdmissionController::class, 'updateInstitution'])->middleware('permission:school-admission.institutions.manage')->name('admin.school-admission.institutions.update');
        Route::post('/products', [AdminSchoolAdmissionController::class, 'storeProduct'])->middleware('permission:school-admission.products.manage')->name('admin.school-admission.products.store');
        Route::put('/products/{product}', [AdminSchoolAdmissionController::class, 'updateProduct'])->middleware('permission:school-admission.products.manage')->name('admin.school-admission.products.update');
        Route::post('/products/{product}/toggle', [AdminSchoolAdmissionController::class, 'toggleProduct'])->middleware('permission:school-admission.products.manage')->name('admin.school-admission.products.toggle');
    });