<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SocialAccountController;
Route::middleware(['auth:sanctum','ensure.addon:social.accounts-verification'])->prefix('/api/v1/social')->group(function(){
 Route::get('/accounts',[SocialAccountController::class,'index']);
});