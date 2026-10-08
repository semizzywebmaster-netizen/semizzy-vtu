<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Education\Http\Controllers\SchoolAdmissionController;
Route::middleware(['api','ensure.addon:education'])->prefix('api/v1/education/admission')->group(function(){
 Route::get('/lookup',[SchoolAdmissionController::class,'lookup'])->name('api.education.admission.lookup');
});