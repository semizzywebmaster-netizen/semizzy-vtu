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
 Route::post('/requirements',[AdminSchoolAdmissionController::class,'requirement'])->middleware('permission:education.school_admission.research')->name('admin.education.school-admission.requirements.store');
 Route::post('/requirements/{requirement}/verify',[AdminSchoolAdmissionController::class,'verifyRequirement'])->middleware('permission:education.school_admission.verify')->name('admin.education.school-admission.requirements.verify');
 Route::post('/programme-admissions/{admission}/verify',[AdminSchoolAdmissionController::class,'verify'])->middleware('permission:education.school_admission.verify')->name('admin.education.school-admission.admissions.verify');
 Route::post('/programme-admissions/{admission}/publish',[AdminSchoolAdmissionController::class,'publish'])->middleware('permission:education.school_admission.publish')->name('admin.education.school-admission.admissions.publish');
 Route::post('/programme-admissions/{admission}/archive',[AdminSchoolAdmissionController::class,'archive'])->middleware('permission:education.school_admission.archive')->name('admin.education.school-admission.admissions.archive');
 Route::post('/cutoffs',[AdminSchoolAdmissionController::class,'cutoff'])->middleware('permission:education.school_admission.research')->name('admin.education.school-admission.cutoffs.store');
 Route::post('/screening',[AdminSchoolAdmissionController::class,'screening'])->middleware('permission:education.school_admission.research')->name('admin.education.school-admission.screening.store');
 Route::post('/sync-routes',[AdminSchoolAdmissionController::class,'seedRoutes'])->middleware('permission:education.school_admission.manage')->name('admin.education.school-admission.routes.sync');
});