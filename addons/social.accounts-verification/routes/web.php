<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SocialServicesController;
Route::middleware(['web','auth','ensure.addon:social.accounts-verification'])->group(function(){
 Route::get('/social-services',[SocialServicesController::class,'index'])->middleware('permission:social.view');
 Route::post('/social-services/accounts/{inventory}/buy',[SocialServicesController::class,'buyAccount'])->middleware(['permission:social.orders.manage','throttle:10,1']);
 Route::post('/social-services/numbers/{inventory}/buy',[SocialServicesController::class,'buyNumber'])->middleware(['permission:social.orders.manage','throttle:10,1']);
 Route::post('/social-services/orders/{order}/pay',[SocialServicesController::class,'pay'])->middleware(['permission:social.orders.manage','throttle:10,1']);
 Route::get('/social-services/numbers/{order}/sms',[SocialServicesController::class,'sms'])->middleware(['permission:social.sms.view','throttle:60,1']);
});