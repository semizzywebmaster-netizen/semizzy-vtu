<?php

use App\Http\Controllers\Admin\VtuAdminController;
use App\Http\Controllers\Admin\VtuConversionAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:ADMIN,STAFF,SUPPORT', 'verified', 'ensure.addon:vtu.digital-services'])
    ->prefix('admin/vtu')
    ->group(function (): void {
        Route::get('/schedule-policy', [VtuAdminController::class, 'schedulePolicy'])->middleware('permission:vtu.settings.manage')->name('admin.vtu.schedule-policy');
        Route::post('/schedule-policy', [VtuAdminController::class, 'saveSchedulePolicy'])->middleware('permission:vtu.settings.manage')->name('admin.vtu.schedule-policy.save');
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
        Route::post('/bulk/{bulk}/archive', [VtuAdminController::class, 'archiveBulk'])->middleware('permission:vtu.bulk.manage')->name('admin.vtu.bulk.archive');
        Route::post('/bulk/{bulk}/unarchive', [VtuAdminController::class, 'unarchiveBulk'])->middleware('permission:vtu.bulk.manage')->name('admin.vtu.bulk.unarchive');
        Route::get('/bulk/export', [VtuAdminController::class, 'exportBulk'])->middleware('permission:vtu.bulk.manage')->name('admin.vtu.bulk.export');
        Route::get('/bulk/export-selected', [VtuAdminController::class, 'exportSelectedBulk'])->middleware('permission:vtu.bulk.manage')->name('admin.vtu.bulk.export-selected');
        Route::post('/bulk/audit-selected', [VtuAdminController::class, 'auditSelectedBulk'])->middleware('permission:vtu.bulk.manage')->name('admin.vtu.bulk.audit-selected');
        Route::post('/bulk/archive-selected', [VtuAdminController::class, 'archiveSelectedBulk'])->middleware('permission:vtu.bulk.manage')->name('admin.vtu.bulk.archive-selected');
        Route::post('/bulk/cancel-selected', [VtuAdminController::class, 'cancelSelectedBulk'])->middleware('permission:vtu.bulk.manage')->name('admin.vtu.bulk.cancel-selected');
        Route::post('/bulk/reconcile-selected', [VtuAdminController::class, 'reconcileSelectedBulk'])->middleware('permission:vtu.bulk.manage')->name('admin.vtu.bulk.reconcile-selected');
        Route::post('/bulk/{bulk}/reconcile', [VtuAdminController::class, 'reconcileBulk'])->middleware('permission:vtu.bulk.manage')->name('admin.vtu.bulk.reconcile');
        Route::get('/bulk/{bulk}/statement', [VtuAdminController::class, 'bulkStatement'])->middleware('permission:vtu.bulk.manage')->name('admin.vtu.bulk.statement');
        Route::get('/bulk/{bulk}/report', [VtuAdminController::class, 'bulkReport'])->middleware('permission:vtu.bulk.manage')->name('admin.vtu.bulk.report');
        Route::get('/bulk/{bulk}/audit', [VtuAdminController::class, 'auditBulk'])->middleware('permission:vtu.bulk.manage')->name('admin.vtu.bulk.audit');
        Route::post('/bulk/{bulk}/cancel', [VtuAdminController::class, 'cancelBulk'])->middleware('permission:vtu.bulk.manage')->name('admin.vtu.bulk.cancel');
        Route::post('/bulk/{bulk}/requery-items', [VtuAdminController::class, 'requeryBulkItem'])->middleware('permission:vtu.requery')->name('admin.vtu.bulk.requery-items');
        Route::post('/bulk/recover-stale', [VtuAdminController::class, 'recoverStaleBulk'])->middleware('permission:vtu.bulk.manage')->name('admin.vtu.bulk.recover-stale');
        Route::get('/transactions', [VtuAdminController::class, 'transactions'])->middleware('permission:vtu.transactions.view')->name('admin.vtu.transactions');
        Route::post('/transactions/bulk/requery', [VtuAdminController::class, 'bulkRequery'])->middleware('permission:vtu.requery')->name('admin.vtu.transactions.bulk-requery');
        Route::post('/transactions/{transaction}/requery', [VtuAdminController::class, 'requery'])->whereNumber('transaction')->middleware('permission:vtu.requery')->name('admin.vtu.transactions.requery');
        Route::post('/transactions/{transaction}/refund', [VtuAdminController::class, 'refund'])->whereNumber('transaction')->middleware(['permission:vtu.refunds.manage','throttle:10,1'])->name('admin.vtu.transactions.refund');

        Route::get('/conversions', [VtuConversionAdminController::class, 'index'])->middleware('permission:vtu.conversions.view')->name('admin.vtu.conversions');
        Route::post('/conversions/settings', [VtuConversionAdminController::class, 'saveSettings'])->middleware('permission:vtu.conversions.manage')->name('admin.vtu.conversions.settings');
        Route::post('/conversions/{conversion}/verify', [VtuConversionAdminController::class, 'verify'])->middleware('permission:vtu.conversions.manage')->name('admin.vtu.conversions.verify');
        Route::post('/conversions/{conversion}/approve', [VtuConversionAdminController::class, 'approve'])->middleware('permission:vtu.conversions.manage')->name('admin.vtu.conversions.approve');
        Route::post('/conversions/{conversion}/reject', [VtuConversionAdminController::class, 'reject'])->middleware('permission:vtu.conversions.manage')->name('admin.vtu.conversions.reject');
    });