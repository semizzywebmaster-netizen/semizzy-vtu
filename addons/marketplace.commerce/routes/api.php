<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Marketplace\Http\Controllers\MarketplaceController;

Route::middleware(['api','auth:sanctum','ensure.addon:marketplace.commerce','transaction.pin'])->group(function(){
 Route::middleware('permission:marketplace.buy')->group(function(){
  Route::post('/api/v1/marketplace/orders',[MarketplaceController::class,'store'])->name('api.marketplace.orders.store');
  Route::post('/api/v1/marketplace/orders/{order}/pay',[MarketplaceController::class,'pay'])->name('api.marketplace.orders.pay');
  Route::post('/api/v1/marketplace/orders/{order}/review',[MarketplaceController::class,'reviewStore'])->name('api.marketplace.orders.review');
  Route::post('/api/v1/marketplace/orders/{order}/cancel',[MarketplaceController::class,'cancel'])->name('api.marketplace.orders.cancel');
  Route::get('/api/v1/marketplace/digital-deliveries/{token}',[MarketplaceController::class,'downloadDigitalAsset'])->withoutMiddleware('transaction.pin')->name('api.marketplace.digital.download');
  Route::post('/api/v1/marketplace/orders/{order}/service/revision',[MarketplaceController::class,'serviceRevision'])->name('api.marketplace.service.revision');
  Route::post('/api/v1/marketplace/orders/{order}/service/accept',[MarketplaceController::class,'serviceAccept'])->name('api.marketplace.service.accept');
  Route::post('/api/v1/marketplace/orders/{order}/service/milestones',[MarketplaceController::class,'serviceMilestones'])->name('api.marketplace.service.milestones');
 });
 Route::middleware('permission:marketplace.sell')->post('/api/v1/marketplace/products/{product}/digital-assets',[MarketplaceController::class,'addDigitalAsset'])->name('api.marketplace.digital.assets.store');
 Route::middleware('permission:marketplace.sell')->post('/api/v1/marketplace/orders/{order}/service/submit',[MarketplaceController::class,'serviceSubmit'])->name('api.marketplace.service.submit');
 Route::middleware('permission:marketplace.orders.manage')->post('/api/v1/marketplace/orders/{order}/refund',[MarketplaceController::class,'refund'])->name('api.marketplace.orders.refund');
});
