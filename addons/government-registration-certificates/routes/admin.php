<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminGovernmentServicesController;
Route::middleware(['web','auth','ensure.addon:government.registration-certificates'])->prefix('/admin/government-services')->group(function(){
 Route::get('/',[AdminGovernmentServicesController::class,'index'])->middleware('permission:government.view');
 Route::post('/services',[AdminGovernmentServicesController::class,'storeService'])->middleware(['permission:government.services.manage','throttle:30,1']);
 Route::patch('/services/{service}',[AdminGovernmentServicesController::class,'updateService'])->middleware(['permission:government.services.manage','throttle:60,1']);
 Route::get('/applications/{application}',[AdminGovernmentServicesController::class,'showApplication'])->middleware('permission:government.orders.manage');
 Route::patch('/applications/{application}/status',[AdminGovernmentServicesController::class,'updateApplicationStatus'])->middleware(['permission:government.orders.manage','throttle:60,1']);
 Route::patch('/documents/{document}/review',[AdminGovernmentServicesController::class,'reviewDocument'])->middleware(['permission:government.documents.manage','throttle:60,1']);
 Route::post('/applications/{application}/certificates',[AdminGovernmentServicesController::class,'storeCertificate'])->middleware(['permission:government.orders.manage','throttle:20,1']);
 Route::patch('/certificates/{certificate}',[AdminGovernmentServicesController::class,'updateCertificate'])->middleware(['permission:government.orders.manage','throttle:60,1']);
});