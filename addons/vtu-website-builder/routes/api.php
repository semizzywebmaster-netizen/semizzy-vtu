<?php
use Illuminate\\Support\\Facades\\Route;
use Addons\\VtuWebsiteBuilder\\Http\\Controllers\\WebsiteBuilderController;
Route::middleware(['auth:sanctum','ensure.addon:vtu.website-builder'])->prefix('api/website-builder')->group(function(){
 Route::get('/sites',[WebsiteBuilderController::class,'index'])->middleware('permission:website.view');
});