<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Investments\Http\Controllers\AdminInvestmentsController;

Route::prefix('admin/investments')->middleware(['auth','ensure.addon:investments.wealth','permission:investments.view'])->group(function () {
    Route::get('/', [AdminInvestmentsController::class, 'index'])->name('admin.investments.index');

    Route::post('/products/{product}/toggle', [AdminInvestmentsController::class, 'toggle'])
        ->middleware('permission:investments.manage')
        ->name('admin.investments.product.toggle');

    Route::post('/securities', [AdminInvestmentsController::class, 'storeSecurity'])
        ->middleware('permission:investments.market.manage')
        ->name('admin.investments.security.store');
    Route::post('/securities/{security}/publish', [AdminInvestmentsController::class, 'publishSecurity'])
        ->middleware('permission:investments.market.manage')
        ->name('admin.investments.security.publish');
    Route::post('/securities/{security}/unpublish', [AdminInvestmentsController::class, 'unpublishSecurity'])
        ->middleware('permission:investments.market.manage')
        ->name('admin.investments.security.unpublish');

    Route::post('/providers', [AdminInvestmentsController::class, 'storeProvider'])
        ->middleware('permission:investments.market.manage')
        ->name('admin.investments.provider.store');
    Route::post('/providers/{provider}/verify', [AdminInvestmentsController::class, 'verifyProvider'])
        ->middleware('permission:investments.market.manage')
        ->name('admin.investments.provider.verify');
    Route::post('/providers/{provider}/disable', [AdminInvestmentsController::class, 'disableProvider'])
        ->middleware('permission:investments.market.manage')
        ->name('admin.investments.provider.disable');

    Route::post('/corporate-actions', [AdminInvestmentsController::class, 'storeCorporateAction'])
        ->middleware('permission:investments.market.manage')
        ->name('admin.investments.corporate-action.store');

    Route::post('/securities/{security}/quotes', [AdminInvestmentsController::class, 'storeQuote'])
        ->middleware('permission:investments.market.manage')
        ->name('admin.investments.quote.store');
});
