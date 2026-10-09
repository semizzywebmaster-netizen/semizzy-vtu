<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Education\Http\Controllers\AdminEducationPastQuestionController;
Route::middleware(['web','auth','role:ADMIN,STAFF','ensure.addon:education','permission:education.content.manage'])->prefix('admin/education/past-questions')->group(function(){
 Route::get('/',[AdminEducationPastQuestionController::class,'index'])->name('admin.education.past-questions');
 Route::post('/',[AdminEducationPastQuestionController::class,'store'])->name('admin.education.past-questions.store');
 Route::put('/{item}',[AdminEducationPastQuestionController::class,'update'])->name('admin.education.past-questions.update');
 Route::post('/{item}/publish',[AdminEducationPastQuestionController::class,'publish'])->name('admin.education.past-questions.publish');
 Route::post('/{item}/archive',[AdminEducationPastQuestionController::class,'archive'])->name('admin.education.past-questions.archive');
 Route::delete('/{item}',[AdminEducationPastQuestionController::class,'destroy'])->name('admin.education.past-questions.destroy');
});
