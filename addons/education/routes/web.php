<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Education\Http\Controllers\EducationPastQuestionController;
Route::middleware(['web','auth','ensure.addon:education'])->group(function(){
 Route::get('/education/school-past-questions',[EducationPastQuestionController::class,'school'])->middleware('permission:education.view')->name('education.school-past-questions');
 Route::get('/education/exam-past-questions',[EducationPastQuestionController::class,'exams'])->middleware('permission:education.view')->name('education.exam-past-questions');
 Route::get('/education/past-questions/{item}/preview',[EducationPastQuestionController::class,'preview'])->middleware('permission:education.view')->name('education.past-questions.preview');
 Route::post('/education/past-questions/{item}/purchase',[EducationPastQuestionController::class,'purchase'])->middleware(['permission:education.purchase','transaction.pin'])->name('education.past-questions.purchase');
 Route::get('/education/past-questions/{item}/download',[EducationPastQuestionController::class,'download'])->middleware('permission:education.download')->name('education.past-questions.download');
});
