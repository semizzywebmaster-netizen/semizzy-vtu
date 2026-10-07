<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GovernmentServicesController;
Route::middleware(['web','auth','ensure.addon:government.registration-certificates'])->group(function(){
 Route::get('/government-services',[GovernmentServicesController::class,'index'])->middleware('permission:government.view');
 Route::post('/government-services/{service}/apply',[GovernmentServicesController::class,'apply'])->middleware(['permission:government.orders.manage','throttle:20,1']);
 Route::get('/government-services/applications/{application}',[GovernmentServicesController::class,'show'])->middleware('permission:government.orders.manage');
});