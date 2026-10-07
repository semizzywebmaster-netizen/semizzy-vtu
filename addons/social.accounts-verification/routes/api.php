<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SocialAccountController;
Route::middleware(['auth:sanctum','ensure.addon:social.accounts-verification'])->prefix('/api/v1/social')->group(function(){
 Route::get('/accounts',[SocialAccountController::class,'index']);
 Route::post('/accounts',[SocialAccountController::class,'store'])->middleware('throttle:20,1');
 Route::post('/accounts/{account}/verify',[SocialAccountController::class,'verify'])->middleware('throttle:10,1');
 Route::post('/accounts/{account}/requery',[SocialAccountController::class,'requery'])->middleware('throttle:20,1');
});