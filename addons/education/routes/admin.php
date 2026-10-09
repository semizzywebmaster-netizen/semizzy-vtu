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

use Semizzy\Addons\Education\Http\Controllers\EducationReferenceCatalogueController;
use Semizzy\Addons\Education\Http\Controllers\EducationReferenceImportController;
Route::middleware(['web','auth','role:ADMIN','ensure.addon:education','permission:education.content.manage'])->prefix('admin/education/references')->group(function(){
 Route::get('/',[EducationReferenceCatalogueController::class,'index'])->name('admin.education.references');
 Route::post('/',[EducationReferenceCatalogueController::class,'store'])->name('admin.education.references.store');
 Route::put('/{id}',[EducationReferenceCatalogueController::class,'update'])->whereNumber('id')->name('admin.education.references.update');
 Route::delete('/{id}',[EducationReferenceCatalogueController::class,'destroy'])->whereNumber('id')->name('admin.education.references.destroy');
 Route::post('/categories',[EducationReferenceCatalogueController::class,'storeCategory'])->name('admin.education.references.categories.store');
 Route::put('/categories/{id}',[EducationReferenceCatalogueController::class,'updateCategory'])->whereNumber('id')->name('admin.education.references.categories.update');
 Route::post('/import-csv',[EducationReferenceImportController::class,'importCsv'])->name('admin.education.references.import-csv');
});
