<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Exams\Http\Controllers\ExamResultController;
Route::middleware(['api','auth:sanctum','ensure.addon:exams.results','permission:exams.check'])->prefix('api/v1/exams/results')->group(function(){
 Route::post('/check',[ExamResultController::class,'check'])->middleware('transaction.pin');
});
