<?php
use Illuminate\Support\Facades\Route;
use Addons\BusinessAgentMerchantReseller\Http\Controllers\BusinessController;
Route::middleware('auth:sanctum')->prefix('api/v1/business')->group(function(){Route::get('/partners',[BusinessController::class,'partners']);});
