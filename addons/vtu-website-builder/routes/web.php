<?php
use Illuminate\\Support\\Facades\\Route;
use Addons\\VtuWebsiteBuilder\\Http\\Controllers\\WebsiteBuilderController;
Route::middleware(['auth','verified','ensure.addon:vtu.website-builder'])->group(function(){
 Route::get('/website-builder',[WebsiteBuilderController::class,'index'])->middleware('permission:website.view')->name('website-builder.index');
 Route::post('/website-builder',[WebsiteBuilderController::class,'store'])->middleware('permission:website.manage')->name('website-builder.store');
 Route::get('/website-builder/sites/{site}/pages/{page}',[WebsiteBuilderController::class,'page'])->middleware('permission:website.view');
 Route::patch('/website-builder/sites/{site}/pages/{page}',[WebsiteBuilderController::class,'savePage'])->middleware('permission:website.manage');
 Route::post('/website-builder/sites/{site}/publish',[WebsiteBuilderController::class,'publish'])->middleware('permission:website.publish');
 Route::post('/website-builder/sites/{site}/domains',[WebsiteBuilderController::class,'domain'])->middleware('permission:website.domains.manage');
});