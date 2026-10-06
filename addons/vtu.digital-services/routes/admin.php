<?php

use App\Http\Controllers\Admin\VtuAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:ADMIN,STAFF,SUPPORT', 'verified', 'ensure.addon:vtu.digital-services'])
    ->prefix('admin/vtu')
    ->group(function (): void {
            Route::middleware('ensure.vtu')->prefix('vtu')->group(function (): void {
                Route::get('/', [VtuAdminController::class, 'dashboard'])->middleware('permission:vtu.view')->name('admin.vtu.dashboard');
                Route::get('/services', [VtuAdminController::class, 'services'])->middleware('permission:vtu.services.manage')->name('admin.vtu.services');
                Route::post('/services/bootstrap', [VtuAdminController::class, 'bootstrap'])->middleware('permission:vtu.services.manage')->name('admin.vtu.services.bootstrap');
                Route::post('/services/{service}/enable', [VtuAdminController::class, 'enableService'])->whereNumber('service')->middleware('permission:vtu.services.manage')->name('admin.vtu.services.enable');
                Route::post('/services/{service}/disable', [VtuAdminController::class, 'disableService'])->whereNumber('service')->middleware('permission:vtu.services.manage')->name('admin.vtu.services.disable');
                Route::post('/services/bulk/toggle', [VtuAdminController::class, 'bulkToggleServices'])->middleware('permission:vtu.services.manage')->name('admin.vtu.services.bulk-toggle');
                Route::get('/products', [VtuAdminController::class, 'products'])->middleware('permission:vtu.products.manage')->name('admin.vtu.products');
                Route::get('/mappings', [VtuAdminController::class, 'mappings'])->middleware('permission:vtu.mappings.manage')->name('admin.vtu.mappings');
                Route::post('/mappings', [VtuAdminController::class, 'saveMapping'])->middleware('permission:vtu.mappings.manage')->name('admin.vtu.mappings.save');
                Route::post('/mappings/bulk/toggle', [VtuAdminController::class, 'bulkToggleMappings'])->middleware('permission:vtu.mappings.manage')->name('admin.vtu.mappings.bulk-toggle');
                Route::post('/products/{product}/enable', [VtuAdminController::class, 'enableProduct'])->whereNumber('product')->middleware('permission:vtu.products.manage')->name('admin.vtu.products.enable');
                Route::post('/products/{product}/disable', [VtuAdminController::class, 'disableProduct'])->whereNumber('product')->middleware('permission:vtu.products.manage')->name('admin.vtu.products.disable');
                Route::post('/products/bulk/toggle', [VtuAdminController::class, 'bulkToggleProducts'])->middleware('permission:vtu.products.manage')->name('admin.vtu.products.bulk-toggle');
                Route::get('/bulk', [VtuAdminController::class, 'bulkOperations'])->middleware('permission:vtu.bulk.manage')->name('admin.vtu.bulk');
                // Keep the static bulk endpoint before the {bulk} wildcard route.
                Route::post('/bulk/reconcile-selected', [VtuAdminController::class, 'reconcileSelectedBulk'])->middleware('permission:vtu.bulk.manage')->name('admin.vtu.bulk.reconcile-selected');
                Route::post('/bulk/{bulk}/reconcile', [VtuAdminController::class, 'reconcileBulk'])->middleware('permission:vtu.bulk.manage')->name('admin.vtu.bulk.reconcile');
                Route::get('/transactions', [VtuAdminController::class, 'transactions'])->middleware('permission:vtu.transactions.view')->name('admin.vtu.transactions');
                Route::post('/transactions/bulk/requery', [VtuAdminController::class, 'bulkRequery'])->middleware('permission:vtu.requery')->name('admin.vtu.transactions.bulk-requery');
                Route::post('/transactions/{transaction}/requery', [VtuAdminController::class, 'requery'])->whereNumber('transaction')->middleware('permission:vtu.requery')->name('admin.vtu.transactions.requery');
                Route::post('/transactions/{transaction}/refund', [VtuAdminController::class, 'refund'])->whereNumber('transaction')->middleware(['permission:vtu.refunds.manage','throttle:10,1'])->name('admin.vtu.transactions.refund');
            });
    
});
