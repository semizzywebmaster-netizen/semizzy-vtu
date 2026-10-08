<?php
use Illuminate\Support\Facades\Route;
use Addons\VtuWebsiteBuilder\Http\Controllers\WebsiteBuilderController;

Route::get('/sites/{site:slug}/{page?}',[WebsiteBuilderController::class,'publicSite'])->where('page','[A-Za-z0-9\-]+')->name('website-builder.public');

Route::middleware(['auth','verified','ensure.addon:vtu.website-builder'])->group(function(){
 Route::get('/website-builder',[WebsiteBuilderController::class,'index'])->middleware('permission:website.view')->name('website-builder.index');
 Route::post('/website-builder',[WebsiteBuilderController::class,'store'])->middleware('permission:website.manage')->name('website-builder.store');
 Route::get('/website-builder/sites/{site}/pages/{page}',[WebsiteBuilderController::class,'pageEditor'])->middleware('permission:website.view')->name('website-builder.page');
 Route::get('/website-builder/sites/{site}/pages/{page}/data',[WebsiteBuilderController::class,'page'])->middleware('permission:website.view');
 Route::get('/website-builder/sites/{site}/pages/{page}/revisions',[WebsiteBuilderController::class,'revisions'])->middleware('permission:website.view');
 Route::post('/website-builder/sites/{site}/pages/{page}/revisions/{revision}/restore',[WebsiteBuilderController::class,'restoreRevision'])->middleware('permission:website.manage');
 Route::patch('/website-builder/sites/{site}',[WebsiteBuilderController::class,'updateSite'])->middleware('permission:website.manage');
 Route::patch('/website-builder/sites/{site}/pages/{page}',[WebsiteBuilderController::class,'savePage'])->middleware('permission:website.manage');
 Route::post('/website-builder/sites/{site}/pages',[WebsiteBuilderController::class,'addPage'])->middleware('permission:website.manage');
 Route::delete('/website-builder/sites/{site}/pages/{page}',[WebsiteBuilderController::class,'deletePage'])->middleware('permission:website.manage');
 Route::post('/website-builder/sites/{site}/pages/{page}/home',[WebsiteBuilderController::class,'setHome'])->middleware('permission:website.manage');
 Route::post('/website-builder/sites/{site}/pages/reorder',[WebsiteBuilderController::class,'reorderPages'])->middleware('permission:website.manage');
 Route::post('/website-builder/sites/{site}/pages/{page}/sections',[WebsiteBuilderController::class,'addSection'])->middleware('permission:website.manage');
 Route::patch('/website-builder/sites/{site}/pages/{page}/sections/{section}',[WebsiteBuilderController::class,'updateSection'])->middleware('permission:website.manage');
 Route::delete('/website-builder/sites/{site}/pages/{page}/sections/{section}',[WebsiteBuilderController::class,'deleteSection'])->middleware('permission:website.manage');
 Route::post('/website-builder/sites/{site}/pages/{page}/sections/reorder',[WebsiteBuilderController::class,'reorderSections'])->middleware('permission:website.manage');
 Route::post('/website-builder/sites/{site}/publish',[WebsiteBuilderController::class,'publish'])->middleware('permission:website.publish');
 Route::post('/website-builder/sites/{site}/domains',[WebsiteBuilderController::class,'domain'])->middleware('permission:website.domains.manage');
 Route::post('/website-builder/sites/{site}/subdomain',[WebsiteBuilderController::class,'subdomain'])->middleware('permission:website.domains.manage');
 Route::post('/website-builder/sites/{site}/domains/{domain}/primary',[WebsiteBuilderController::class,'setPrimaryDomain'])->middleware('permission:website.domains.manage');
 Route::post('/website-builder/sites/{site}/domains/{domain}/verify',[WebsiteBuilderController::class,'verifyDomain'])->middleware('permission:website.domains.manage');
 Route::get('/website-builder/sites/{site}/preview/{page?}',[WebsiteBuilderController::class,'preview'])->middleware('permission:website.view')->name('website-builder.preview');
});