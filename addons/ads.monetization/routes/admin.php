<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Ads\Http\Controllers\AdsAdminController;

Route::middleware(['web','auth','ensure.addon:ads.monetization','permission:ads.view'])->group(function () {
 Route::get('/admin/ads',[AdsAdminController::class,'index'])->name('admin.ads.index');
});
Route::middleware(['web','auth','ensure.addon:ads.monetization','permission:ads.campaigns.manage'])->group(function () {
 Route::post('/admin/ads/campaigns/{campaign}/review',[AdsAdminController::class,'review'])->name('admin.ads.campaigns.review');
});
Route::middleware(['web','auth','ensure.addon:ads.monetization','permission:ads.placements.manage'])->group(function () {
 Route::post('/admin/ads/placements',[AdsAdminController::class,'storePlacement'])->name('admin.ads.placements.store');
 Route::patch('/admin/ads/placements/{placement}',[AdsAdminController::class,'updatePlacement'])->name('admin.ads.placements.update');
});
Route::middleware(['web','auth','ensure.addon:ads.monetization','permission:ads.settings.manage'])->group(function () {
 Route::get('/admin/ads/types',[AdsAdminController::class,'adTypes'])->name('admin.ads.types');
 Route::post('/admin/ads/types',[AdsAdminController::class,'storeAdType'])->name('admin.ads.types.store');
 Route::patch('/admin/ads/types/{adType}',[AdsAdminController::class,'updateAdType'])->name('admin.ads.types.update');
});
Route::middleware(['web','auth','ensure.addon:ads.monetization','permission:ads.promotions.manage'])->group(function () {
 Route::get('/admin/ads/promotions',[AdsAdminController::class,'promotions'])->name('admin.ads.promotions');
 Route::post('/admin/ads/promotions/packages',[AdsAdminController::class,'createPromotionPackage'])->name('admin.ads.promotions.packages.store');
 Route::post('/admin/ads/promotions/{promotion}/review',[AdsAdminController::class,'reviewPromotion'])->name('admin.ads.promotions.review');
});
