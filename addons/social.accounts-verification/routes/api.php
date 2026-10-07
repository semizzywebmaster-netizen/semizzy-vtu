<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SocialServicesController;
Route::middleware(['auth:sanctum','ensure.addon:social.accounts-verification'])->prefix('/api/v1/social-services')->group(function(){
 Route::get('/',[SocialServicesController::class,'index'])->middleware('permission:social.view');
 Route::post('/accounts/{inventory}/buy',[SocialServicesController::class,'buyAccount'])->middleware(['permission:social.orders.manage','throttle:10,1']);
 Route::post('/numbers/{inventory}/buy',[SocialServicesController::class,'buyNumber'])->middleware(['permission:social.orders.manage','throttle:10,1']);
 Route::get('/numbers/{order}/sms',[SocialServicesController::class,'sms'])->middleware(['permission:social.sms.view','throttle:60,1']);
});