<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GovernmentServicesController;
Route::middleware(['auth:sanctum','ensure.addon:government.registration-certificates'])->prefix('/api/v1/government-services')->group(function(){
 Route::get('/',[GovernmentServicesController::class,'index'])->middleware('permission:government.view');
 Route::post('/{service}/apply',[GovernmentServicesController::class,'apply'])->middleware(['permission:government.orders.manage','throttle:20,1']);
 Route::get('/applications/{application}',[GovernmentServicesController::class,'show'])->middleware('permission:government.orders.manage');
});