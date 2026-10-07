<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Exams\Http\Controllers\ExamResultController;
Route::middleware(['web','auth','ensure.addon:exams.results'])->group(function(){
 Route::get('/exams/results',[ExamResultController::class,'index'])->middleware('permission:exams.view')->name('exams.results');
 Route::post('/exams/results/check',[ExamResultController::class,'check'])->middleware(['permission:exams.check','transaction.pin'])->name('exams.results.check');
});
