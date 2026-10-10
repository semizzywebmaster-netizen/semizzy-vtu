<?php

use Illuminate\Support\Facades\Route;
use Semizzy\Addons\ApiProviderPlatform\Http\Controllers\ProviderPlatformAdminController;

Route::middleware([
    'auth',
    'verified',
    'role:ADMIN,STAFF,SUPPORT',
    'ensure.addon:api.provider-platform',
    'permission:provider_platform.view',
])->get('/admin/provider-platform', [ProviderPlatformAdminController::class, 'index'])
    ->name('admin.provider-platform.index');
