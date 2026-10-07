<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Exams\Http\Controllers\ExamResultController;
Route::middleware(['web','auth','ensure.addon:exams.results','permission:exams.view'])->group(function(){
 Route::get('/exams/results',[ExamResultController::class,'index'])->name('exams.results');
});
