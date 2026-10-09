<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Ads\Http\Controllers\AdsAdminController;

Route::middleware(['web','auth','ensure.addon:ads.monetization','permission:ads.view'])->group(function () {
 Route::get('/admin/ads',[AdsAdminController::class,'index'])->name('admin.ads.index');
});
Route::middleware(['web','auth','ensure.addon:ads.monetization','permission:ads.campaigns.manage'])->group(function () {
 Route::post('/admin/ads/campaigns/{campaign}/review',[AdsAdminController::class,'review'])->name('admin.ads.campaigns.review');
 Route::post('/admin/ads/placements',[AdsAdminController::class,'storePlacement'])->name('admin.ads.placements.store');
 Route::patch('/admin/ads/placements/{placement}',[AdsAdminController::class,'updatePlacement'])->name('admin.ads.placements.update');
});
