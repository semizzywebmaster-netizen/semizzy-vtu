<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Education\Http\Controllers\AdminSchoolAdmissionController;
Route::middleware(['web','auth','role:ADMIN,STAFF','ensure.addon:education'])->prefix('admin/education/school-admission')->group(function(){
 Route::get('/',[AdminSchoolAdmissionController::class,'index'])->middleware('permission:education.school_admission.view')->name('admin.education.school-admission');
 Route::post('/academic-units',[AdminSchoolAdmissionController::class,'unit'])->middleware('permission:education.school_admission.manage')->name('admin.education.school-admission.units.store');
 Route::post('/departments',[AdminSchoolAdmissionController::class,'department'])->middleware('permission:education.school_admission.manage')->name('admin.education.school-admission.departments.store');
 Route::post('/programmes',[AdminSchoolAdmissionController::class,'programme'])->middleware('permission:education.school_admission.manage')->name('admin.education.school-admission.programmes.store');
 Route::post('/sessions',[AdminSchoolAdmissionController::class,'session'])->middleware('permission:education.school_admission.manage')->name('admin.education.school-admission.sessions.store');
 Route::post('/programme-admissions',[AdminSchoolAdmissionController::class,'admission'])->middleware('permission:education.school_admission.research')->name('admin.education.school-admission.admissions.store');
 Route::post('/sync-routes',[AdminSchoolAdmissionController::class,'seedRoutes'])->middleware('permission:education.school_admission.manage')->name('admin.education.school-admission.routes.sync');
});