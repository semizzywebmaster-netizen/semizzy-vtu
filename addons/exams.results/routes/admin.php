<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Exams\Http\Controllers\AdminExamResultController;
Route::middleware(['web','auth','role:ADMIN,STAFF,SUPPORT','ensure.addon:exams.results','permission:exams.view'])->prefix('admin/exams/results')->group(function(){
 Route::get('/',[AdminExamResultController::class,'index'])->name('admin.exams.results');
});
