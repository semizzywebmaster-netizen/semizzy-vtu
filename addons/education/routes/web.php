<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Education\Http\Controllers\SchoolAdmissionController;
Route::middleware(['web','auth','ensure.addon:education'])->prefix('education/admission')->group(function(){
 Route::get('/',[SchoolAdmissionController::class,'index'])->middleware('permission:education.school_admission.view')->name('education.admission');
 Route::get('/programmes/{admission}',[SchoolAdmissionController::class,'show'])->middleware('permission:education.school_admission.view')->name('education.admission.show');
 Route::get('/lookup',[SchoolAdmissionController::class,'lookup'])->middleware('permission:education.school_admission.view')->name('education.admission.lookup');
});