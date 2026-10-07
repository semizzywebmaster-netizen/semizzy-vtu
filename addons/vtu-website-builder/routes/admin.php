<?php
use Illuminate\\Support\\Facades\\Route;
use Addons\\VtuWebsiteBuilder\\Http\\Controllers\\WebsiteBuilderController;
Route::middleware(['auth','verified','ensure.addon:vtu.website-builder','permission:website.view'])->group(function(){
 Route::get('/admin/website-builder',[WebsiteBuilderController::class,'index'])->name('admin.website-builder');
});