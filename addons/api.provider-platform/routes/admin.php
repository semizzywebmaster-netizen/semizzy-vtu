<?php

use Illuminate\Support\Facades\Route;
use Semizzy\Addons\ApiProviderPlatform\Http\Controllers\ProviderPlatformAdminController;

Route::middleware([
    'auth',
    'verified',
    'role:ADMIN,STAFF,SUPPORT',
    'ensure.addon:api.provider-platform',
    'permission:provider_platform.view',
])->group(function (): void {
    Route::get('/admin/provider-platform', [ProviderPlatformAdminController::class, 'index'])
        ->name('admin.provider-platform.index');
    Route::get('/admin/provider-platform/catalogue/review', [ProviderPlatformAdminController::class, 'cataloguePage'])
        ->name('admin.provider-platform.catalogue.review');
    Route::get('/admin/provider-platform/catalogue', [ProviderPlatformAdminController::class, 'catalogue'])
        ->name('admin.provider-platform.catalogue');
    Route::post('/admin/provider-platform/catalogue/{providerService}/approve', [ProviderPlatformAdminController::class, 'approveCatalogueService'])
        ->middleware('permission:provider_platform.manage')
        ->name('admin.provider-platform.catalogue.approve');
    Route::post('/admin/provider-platform/catalogue/{providerService}/map', [ProviderPlatformAdminController::class, 'mapCatalogueService'])
        ->middleware('permission:provider_platform.manage')
        ->name('admin.provider-platform.catalogue.map');
    Route::post('/admin/provider-platform/catalogue/{providerService}/select', [ProviderPlatformAdminController::class, 'selectCatalogueService'])
        ->middleware('permission:provider_platform.manage')
        ->name('admin.provider-platform.catalogue.select');
    Route::patch('/admin/provider-platform/products/{product}/publish', [ProviderPlatformAdminController::class, 'publishProduct'])
        ->middleware('permission:provider_platform.manage')
        ->name('admin.provider-platform.products.publish');
    Route::patch('/admin/provider-platform/products/{product}/unpublish', [ProviderPlatformAdminController::class, 'unpublishProduct'])
        ->middleware('permission:provider_platform.manage')
        ->name('admin.provider-platform.products.unpublish');
});
