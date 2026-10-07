<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminSocialServicesController;
Route::middleware(['web','auth','ensure.addon:social.accounts-verification','role:ADMIN,STAFF'])->prefix('/admin/social-services')->group(function(){
 Route::get('/',[AdminSocialServicesController::class,'index'])->middleware('permission:social.view');
 Route::post('/accounts',[AdminSocialServicesController::class,'account'])->middleware(['permission:social.accounts.manage','throttle:30,1']);
 Route::post('/numbers',[AdminSocialServicesController::class,'number'])->middleware(['permission:social.numbers.manage','throttle:30,1']);
 Route::post('/orders/{order}/sms',[AdminSocialServicesController::class,'sms'])->middleware(['permission:social.sms.manage','throttle:60,1']);
});