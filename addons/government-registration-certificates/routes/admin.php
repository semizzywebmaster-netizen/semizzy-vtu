<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminGovernmentServicesController;
Route::middleware(['web','auth','ensure.addon:government.registration-certificates'])->prefix('/admin/government-services')->group(function(){
 Route::get('/',[AdminGovernmentServicesController::class,'index'])->middleware('permission:government.view');
 Route::post('/services',[AdminGovernmentServicesController::class,'storeService'])->middleware(['permission:government.services.manage','throttle:30,1']);
 Route::patch('/services/{service}',[AdminGovernmentServicesController::class,'updateService'])->middleware(['permission:government.services.manage','throttle:60,1']);
 Route::patch('/applications/{application}/status',[AdminGovernmentServicesController::class,'updateApplicationStatus'])->middleware(['permission:government.orders.manage','throttle:60,1']);
 Route::patch('/documents/{document}/review',[AdminGovernmentServicesController::class,'reviewDocument'])->middleware(['permission:government.documents.manage','throttle:60,1']);
});