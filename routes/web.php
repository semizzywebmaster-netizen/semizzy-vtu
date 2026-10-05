<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\AddonController;
use App\Http\Controllers\Admin\AuditEventController;
use App\Http\Controllers\Admin\CatalogueController;
use App\Http\Controllers\Admin\ProviderController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\SecurityEventController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\UpdatePasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\VtuController;
use App\Http\Controllers\Admin\VtuAdminController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome', [
    'appName' => config('app.name', 'SEMIZZY ONE'),
]))->name('home');

Route::get('/setup', [SetupController::class, 'index'])->name('setup');
Route::post('/setup/key', [SetupController::class, 'generateKey'])->middleware('throttle:3,1')->name('setup.key');
Route::post('/setup/migrate', [SetupController::class, 'migrate'])->middleware('throttle:5,1')->name('setup.migrate');
Route::post('/setup/admin', [SetupController::class, 'createAdmin'])->middleware('throttle:5,1')->name('setup.admin');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:5,1')->name('password.update');
});

Route::get('/email/verify', fn () => Inertia::render('Auth/VerifyEmail'))->middleware('auth')->name('verification.notice');
Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)->middleware(['auth', 'signed', 'throttle:6,1'])->name('verification.verify');

Route::post('/email/verification-notification', function (\Illuminate\Http\Request $request) {
    $request->user()->sendEmailVerificationNotification();

    return back()->with('success', 'Verification email sent.');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

$adminLoginPath = trim((string) config('semizzy.admin_login_path', 'admin/login'), '/') ?: 'admin/login';
Route::get('/'.$adminLoginPath, [AuthenticatedSessionController::class, 'createAdmin'])->middleware('guest')->name('admin.login');
Route::post('/'.$adminLoginPath, [AuthenticatedSessionController::class, 'storeAdmin'])->middleware('guest')->name('admin.login.store');

Route::middleware(['auth'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->middleware('verified')->name('dashboard');
    Route::middleware(['verified','ensure.vtu'])->group(function (): void { Route::get('/vtu', [VtuController::class, 'index'])->middleware('permission:vtu.view')->name('vtu.services'); });
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->middleware('throttle:30,1')->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->middleware('throttle:60,1')->name('notifications.read');
    Route::post('/profile/password', [UpdatePasswordController::class, 'store'])->middleware('throttle:5,1')->name('profile.password.update');
    Route::get('/support', [SupportTicketController::class, 'index'])->name('support.index');
    Route::post('/support', [SupportTicketController::class, 'store'])->middleware('throttle:10,1')->name('support.store');
    Route::get('/support/{ticket}', [SupportTicketController::class, 'show'])->whereNumber('ticket')->name('support.show');
    Route::post('/support/{ticket}/reply', [SupportTicketController::class, 'reply'])->whereNumber('ticket')->middleware('throttle:20,1')->name('support.reply');
    Route::patch('/support/{ticket}/status', [SupportTicketController::class, 'updateStatus'])->whereNumber('ticket')->middleware('throttle:30,1')->name('support.status');

    Route::prefix('admin')->middleware(['role:ADMIN,STAFF,SUPPORT', 'verified'])->group(function (): void {
        Route::get('/audit-events', [AuditEventController::class, 'index'])->middleware('permission:audit.view')->name('admin.audit-events.index');
        Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view')->name('admin.users.index');
        Route::patch('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.manage')->name('admin.users.update');
        Route::get('/health', SystemHealthController::class)->middleware('permission:system.view')->name('admin.health');
        Route::get('/settings', [SystemSettingsController::class, 'index'])->middleware('permission:system.manage')->name('admin.settings.index');
        Route::put('/settings', [SystemSettingsController::class, 'update'])->middleware(['permission:system.manage', 'throttle:20,1'])->name('admin.settings.update');
        Route::get('/security-events', [SecurityEventController::class, 'index'])->middleware('permission:security.view')->name('admin.security-events.index');

        Route::get('/providers', [ProviderController::class, 'index'])->middleware('permission:providers.view')->name('admin.providers.index');
        Route::post('/providers/install-presets', [ProviderController::class, 'installPresets'])->middleware('permission:providers.manage')->name('admin.providers.install-presets');
        Route::post('/providers/bulk/test', [ProviderController::class, 'bulkTest'])->middleware('permission:providers.manage')->name('admin.providers.bulk.test');
        Route::post('/providers/bulk/toggle', [ProviderController::class, 'bulkToggle'])->middleware('permission:providers.manage')->name('admin.providers.bulk.toggle');
        Route::delete('/providers/bulk', [ProviderController::class, 'bulkDestroy'])->middleware('permission:providers.manage')->name('admin.providers.bulk.destroy');
        Route::post('/providers', [ProviderController::class, 'store'])->middleware('permission:providers.manage')->name('admin.providers.store');
        Route::patch('/providers/{provider}', [ProviderController::class, 'update'])->whereNumber('provider')->middleware('permission:providers.manage')->name('admin.providers.update');
        Route::post('/providers/{provider}/test', [ProviderController::class, 'test'])->whereNumber('provider')->middleware('permission:providers.manage')->name('admin.providers.test');
        Route::post('/providers/{provider}/toggle', [ProviderController::class, 'toggle'])->whereNumber('provider')->middleware('permission:providers.manage')->name('admin.providers.toggle');
        Route::delete('/providers/{provider}', [ProviderController::class, 'destroy'])->whereNumber('provider')->middleware('permission:providers.manage')->name('admin.providers.destroy');

        Route::get('/catalogue', [CatalogueController::class, 'index'])->middleware('permission:catalogue.view')->name('admin.catalogue.index');
        Route::post('/catalogue/categories', [CatalogueController::class, 'storeCategory'])->middleware('permission:catalogue.manage')->name('admin.catalogue.categories.store');
        Route::post('/catalogue/services', [CatalogueController::class, 'storeService'])->middleware('permission:catalogue.manage')->name('admin.catalogue.services.store');
        Route::post('/catalogue/products', [CatalogueController::class, 'storeProduct'])->middleware('permission:catalogue.manage')->name('admin.catalogue.products.store');
        Route::post('/catalogue/products/{product}/disable', [CatalogueController::class, 'disableProduct'])->middleware('permission:catalogue.manage')->name('admin.catalogue.products.disable');
        Route::post('/catalogue/sync', [CatalogueController::class, 'syncProvider'])->middleware('permission:catalogue.manage')->name('admin.catalogue.sync');
        Route::post('/catalogue/sync-all', [CatalogueController::class, 'syncAllVerified'])->middleware('permission:catalogue.manage')->name('admin.catalogue.sync-all');
        Route::post('/catalogue/mappings/{mapping}/toggle', [CatalogueController::class, 'toggleMapping'])->middleware('permission:catalogue.manage')->name('admin.catalogue.mappings.toggle');

        Route::get('/addons', [AddonController::class, 'index'])->middleware('permission:addons.view')->name('admin.addons.index');
        Route::post('/addons/register-vtu', [AddonController::class, 'registerVtu'])->middleware('permission:addons.manage')->name('admin.addons.register-vtu');
        Route::post('/addons/install-vtu', [AddonController::class, 'installVtu'])->middleware('permission:addons.manage')->name('admin.addons.install-vtu');
        Route::middleware('ensure.vtu')->prefix('vtu')->group(function (): void {
            Route::get('/', [VtuAdminController::class, 'dashboard'])->middleware('permission:vtu.view')->name('admin.vtu.dashboard');
            Route::get('/services', [VtuAdminController::class, 'services'])->middleware('permission:vtu.services.manage')->name('admin.vtu.services');
            Route::post('/services/bootstrap', [VtuAdminController::class, 'bootstrap'])->middleware('permission:vtu.services.manage')->name('admin.vtu.services.bootstrap');
            Route::post('/services/{service}/enable', [VtuAdminController::class, 'enableService'])->middleware('permission:vtu.services.manage')->name('admin.vtu.services.enable');
            Route::post('/services/{service}/disable', [VtuAdminController::class, 'disableService'])->middleware('permission:vtu.services.manage')->name('admin.vtu.services.disable');
            Route::post('/services/bulk/toggle', [VtuAdminController::class, 'bulkToggleServices'])->middleware('permission:vtu.services.manage')->name('admin.vtu.services.bulk-toggle');
            Route::get('/products', [VtuAdminController::class, 'products'])->middleware('permission:vtu.products.manage')->name('admin.vtu.products');
            Route::get('/mappings', [VtuAdminController::class, 'mappings'])->middleware('permission:vtu.mappings.manage')->name('admin.vtu.mappings');
            Route::post('/mappings', [VtuAdminController::class, 'saveMapping'])->middleware('permission:vtu.mappings.manage')->name('admin.vtu.mappings.save');
            Route::post('/mappings/bulk/toggle', [VtuAdminController::class, 'bulkToggleMappings'])->middleware('permission:vtu.mappings.manage')->name('admin.vtu.mappings.bulk-toggle');
            Route::post('/products/{product}/enable', [VtuAdminController::class, 'enableProduct'])->middleware('permission:vtu.products.manage')->name('admin.vtu.products.enable');
            Route::post('/products/{product}/disable', [VtuAdminController::class, 'disableProduct'])->middleware('permission:vtu.products.manage')->name('admin.vtu.products.disable');
            Route::post('/products/bulk/toggle', [VtuAdminController::class, 'bulkToggleProducts'])->middleware('permission:vtu.products.manage')->name('admin.vtu.products.bulk-toggle');
            Route::get('/bulk', [VtuAdminController::class, 'bulkOperations'])->middleware('permission:vtu.bulk.manage')->name('admin.vtu.bulk');
            // Keep the static bulk endpoint before the {bulk} wildcard route.
            Route::post('/bulk/reconcile-selected', [VtuAdminController::class, 'reconcileSelectedBulk'])->middleware('permission:vtu.bulk.manage')->name('admin.vtu.bulk.reconcile-selected');
            Route::post('/bulk/{bulk}/reconcile', [VtuAdminController::class, 'reconcileBulk'])->middleware('permission:vtu.bulk.manage')->name('admin.vtu.bulk.reconcile');
            Route::get('/transactions', [VtuAdminController::class, 'transactions'])->middleware('permission:vtu.transactions.view')->name('admin.vtu.transactions');
            Route::post('/transactions/bulk/requery', [VtuAdminController::class, 'bulkRequery'])->middleware('permission:vtu.requery')->name('admin.vtu.transactions.bulk-requery');
            Route::post('/transactions/{transaction}/requery', [VtuAdminController::class, 'requery'])->middleware('permission:vtu.requery')->name('admin.vtu.transactions.requery');
            Route::post('/transactions/{transaction}/refund', [VtuAdminController::class, 'refund'])->middleware(['permission:vtu.refunds.manage','throttle:10,1'])->name('admin.vtu.transactions.refund');
        });
        Route::post('/addons/register', [AddonController::class, 'register'])->middleware('permission:addons.manage')->name('admin.addons.register');
        Route::post('/addons/{addon}/install', [AddonController::class, 'install'])->middleware('permission:addons.manage')->name('admin.addons.install');
        Route::post('/addons/{addon}/update', [AddonController::class, 'update'])->middleware('permission:addons.manage')->name('admin.addons.update');
        Route::post('/addons/{addon}/activate', [AddonController::class, 'activate'])->middleware('permission:addons.manage')->name('admin.addons.activate');
        Route::post('/addons/{addon}/disable', [AddonController::class, 'disable'])->middleware('permission:addons.manage')->name('admin.addons.disable');
        Route::post('/addons/{addon}/archive', [AddonController::class, 'archive'])->middleware('permission:addons.manage')->name('admin.addons.archive');
        Route::post('/addons/{addon}/uninstall', [AddonController::class, 'uninstall'])->middleware('permission:addons.manage')->name('admin.addons.uninstall');
    });
});
