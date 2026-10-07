<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SocialAccountController;
Route::middleware(['web','auth','ensure.addon:social.accounts-verification'])->group(function(){
 Route::get('/social',[SocialAccountController::class,'index'])->middleware('permission:social.view');
 Route::post('/social/accounts',[SocialAccountController::class,'store'])->middleware(['permission:social.accounts.manage','throttle:20,1']);
 Route::post('/social/accounts/{account}/verify',[SocialAccountController::class,'verify'])->middleware(['permission:social.accounts.manage','throttle:10,1']);
 Route::post('/social/accounts/{account}/requery',[SocialAccountController::class,'requery'])->middleware(['permission:social.requery','throttle:20,1']);
});