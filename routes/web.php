<?php

use App\\Http\\Controllers\\Admin\\AddonController;
use App\\Http\\Controllers\\Admin\\ProviderController;
use App\\Http\\Controllers\\Auth\\AuthenticatedSessionController;
use App\\Http\\Controllers\\Auth\\RegisteredUserController;
use Illuminate\\Support\\Facades\\Route;
use Inertia\\Inertia;

Route::get('/', fn () => Inertia::render('Welcome', [
    'appName' => config('app.name', 'SEMIZZY ONE'),
]))->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
});
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::get('/admin/login', [AuthenticatedSessionController::class, 'createAdmin'])->middleware('guest')->name('admin.login');
Route::post('/admin/login', [AuthenticatedSessionController::class, 'storeAdmin'])->middleware('guest')->name('admin.login.store');

Route::middleware(['auth'])->group(function (): void {
    Route::get('/dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');

    Route::middleware('role:ADMIN,STAFF,SUPPORT')->prefix('admin')->group(function (): void {
        Route::get('/providers', [ProviderController::class, 'index'])->name('admin.providers.index');
        Route::post('/providers', [ProviderController::class, 'store'])->name('admin.providers.store');
        Route::patch('/providers/{provider}', [ProviderController::class, 'update'])->name('admin.providers.update');
        Route::post('/providers/{provider}/toggle', [ProviderController::class, 'toggle'])->name('admin.providers.toggle');

        Route::get('/addons', [AddonController::class, 'index'])->name('admin.addons.index');
        Route::post('/addons/{addon}/activate', [AddonController::class, 'activate'])->name('admin.addons.activate');
        Route::post('/addons/{addon}/disable', [AddonController::class, 'disable'])->name('admin.addons.disable');
    });
});
