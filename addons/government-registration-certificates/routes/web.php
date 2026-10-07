<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GovernmentServicesController;
Route::middleware(['web','auth','ensure.addon:government.registration-certificates'])->group(function(){
 Route::get('/government-services',[GovernmentServicesController::class,'index'])->middleware('permission:government.view');
 Route::post('/government-services/{service}/apply',[GovernmentServicesController::class,'apply'])->middleware(['permission:government.orders.manage','throttle:20,1']);
 Route::post('/government-services/applications/{application}/pay',[GovernmentServicesController::class,'pay'])->middleware(['permission:government.orders.manage','throttle:10,1']);
 Route::post('/government-services/applications/{application}/documents',[GovernmentServicesController::class,'uploadDocument'])->middleware(['permission:government.orders.manage','throttle:20,1']);
 Route::post('/government-services/applications/{application}/provider-submit',[GovernmentServicesController::class,'providerSubmit'])->middleware(['permission:government.orders.manage','throttle:10,1']);
 Route::post('/government-services/applications/{application}/requery',[GovernmentServicesController::class,'requery'])->middleware(['permission:government.orders.manage','throttle:20,1']);
 Route::post('/government-services/applications/{application}/submit',[GovernmentServicesController::class,'submit'])->middleware(['permission:government.orders.manage','throttle:10,1']);
 Route::get('/government-services/applications/{application}',[GovernmentServicesController::class,'show'])->middleware('permission:government.orders.manage');
 Route::get('/government-services/certificates/{certificate}/download',[GovernmentServicesController::class,'downloadCertificate'])->middleware('permission:government.orders.manage');
});