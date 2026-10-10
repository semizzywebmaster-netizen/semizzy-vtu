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

    Route::patch('/admin/provider-platform/products/{product}/publish', [ProviderPlatformAdminController::class, 'publishProduct'])
        ->middleware('permission:provider_platform.manage')
        ->name('admin.provider-platform.products.publish');

    Route::patch('/admin/provider-platform/products/{product}/unpublish', [ProviderPlatformAdminController::class, 'unpublishProduct'])
        ->middleware('permission:provider_platform.manage')
        ->name('admin.provider-platform.products.unpublish');
});
