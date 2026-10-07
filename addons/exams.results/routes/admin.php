<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Exams\Http\Controllers\AdminExamResultController;
Route::middleware(['web','auth','role:ADMIN,STAFF,SUPPORT','ensure.addon:exams.results'])->prefix('admin/exams/results')->group(function(){
 Route::get('/',[AdminExamResultController::class,'index'])->middleware('permission:exams.view')->name('admin.exams.results');
 Route::post('/products',[AdminExamResultController::class,'store'])->middleware('permission:exams.products.manage')->name('admin.exams.results.products.store');
 Route::put('/products/{product}',[AdminExamResultController::class,'update'])->middleware('permission:exams.products.manage')->name('admin.exams.results.products.update');
 Route::post('/products/{product}/toggle',[AdminExamResultController::class,'toggle'])->middleware('permission:exams.products.manage')->name('admin.exams.results.products.toggle');
 Route::delete('/products/{product}',[AdminExamResultController::class,'destroy'])->middleware('permission:exams.products.manage')->name('admin.exams.results.products.destroy');
});
