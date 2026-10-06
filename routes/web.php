<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinancialAnalyticsController;
use App\Http\Controllers\Admin\AddonController;
use App\Http\Controllers\Admin\AuditEventController;
use App\Http\Controllers\Admin\CatalogueController;
use App\Http\Controllers\Admin\ProviderController;
use App\Http\Controllers\Admin\ProviderEngineController;
use App\Http\Controllers\Admin\PricingRoutingController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\SystemMaintenanceController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\SecurityEventController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\PasswordRecoveryController;
use App\Http\Controllers\Auth\UpdatePasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\KycController;
use App\Http\Controllers\Admin\KycController as AdminKycController;
use App\Http\Controllers\Admin\PlatformControlController;
use App\Http\Controllers\ProfileChangeRequestController;
use App\Http\Controllers\Admin\ProfileChangeRequestController as AdminProfileChangeRequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RealtimeController;
use App\Http\Controllers\HelpCenterController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\VtuController;
use App\Http\Controllers\UserTransactionController;
use App\Http\Controllers\WalletFundingController;
use App\Http\Controllers\Admin\VtuAdminController;
use App\Http\Controllers\Admin\CommunicationController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\SecurityOtpController;
use App\Http\Controllers\TransactionPinController;
use App\Http\Controllers\ApiAccessController;
use App\Services\System\SystemSettingsService;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome', [
    'appName' => config('app.name', 'SEMIZZY ONE'),
]))->name('home');

Route::get('/manifest.webmanifest', function (SystemSettingsService $settings) {
    $platform = $settings->all();
    $name = trim((string) ($platform['platform_name'] ?? 'SEMIZZY ONE')) ?: 'SEMIZZY ONE';
    $shortName = mb_substr($name, 0, 24);

    return response()->json([
        'id' => '/',
        'name' => $name,
        'short_name' => $shortName,
        'description' => $name . ' platform',
        'start_url' => '/dashboard',
        'scope' => '/',
        'display' => 'standalone',
        'background_color' => '#f8fafc',
        'theme_color' => $platform['theme_primary'] ?? '#4338ca',
        'orientation' => 'portrait-primary',
        'icons' => [
            [
                'src' => '/icons/semizzy-one.svg',
                'sizes' => 'any',
                'type' => 'image/svg+xml',
                'purpose' => 'any maskable',
            ],
        ],
    ])->header('Cache-Control', 'no-store, max-age=0');
})->name('manifest');

Route::get('/setup', [SetupController::class, 'index'])->name('setup');
Route::post('/setup/key', [SetupController::class, 'generateKey'])->middleware('throttle:3,1')->name('setup.key');
Route::post('/setup/migrate', [SetupController::class, 'migrate'])->middleware('throttle:5,1')->name('setup.migrate');
Route::post('/setup/admin', [SetupController::class, 'createAdmin'])->middleware('throttle:5,1')->name('setup.admin');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
    Route::get('/forgot-password', [PasswordRecoveryController::class, 'create'])->name('password.request');
    Route::post('/forgot-password/otp', [PasswordRecoveryController::class, 'requestOtp'])->middleware('throttle:3,10')->name('password.recovery.otp');
    Route::post('/forgot-password/reset', [PasswordRecoveryController::class, 'reset'])->middleware('throttle:5,10')->name('password.recovery.reset');
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
    Route::get('/admin/communications', [CommunicationController::class, 'index'])->middleware('permission:communications.manage')->name('admin.communications');
    Route::post('/admin/communications', [CommunicationController::class, 'store'])->middleware('permission:communications.manage')->name('admin.communications.store');
    Route::post('/admin/communications/{campaign}/send', [CommunicationController::class, 'send'])->middleware('permission:communications.manage')->name('admin.communications.send');
    Route::get('/dashboard', DashboardController::class)->middleware('verified')->name('dashboard');
    Route::middleware(['verified','ensure.vtu'])->group(function (): void { Route::get('/vtu', [VtuController::class, 'index'])->middleware('permission:vtu.view')->name('vtu.services'); });
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::get('/kyc', [KycController::class, 'index'])->name('kyc.index');
    Route::post('/kyc', [KycController::class, 'submit'])->middleware(['throttle:5,1','transaction.pin'])->name('kyc.submit');
    Route::get('/kyc/document', [KycController::class, 'document'])->name('kyc.document');
    Route::get('/profile/identity-document', [ProfileController::class, 'identityDocument'])->name('profile.identity-document');
    Route::post('/profile', [ProfileController::class, 'update'])->middleware(['throttle:10,1','transaction.pin'])->name('profile.update');
    Route::get('/profile/transaction-pin', [TransactionPinController::class, 'index'])->name('profile.transaction-pin');
    Route::post('/security/otp/request', [SecurityOtpController::class, 'request'])->middleware('throttle:3,10')->name('security.otp.request');
    Route::get('/profile/change-requests', [ProfileChangeRequestController::class, 'index'])->name('profile.change-requests.index');
    Route::post('/profile/change-requests', [ProfileChangeRequestController::class, 'store'])->middleware(['throttle:5,1','transaction.pin'])->name('profile.change-requests.store');
    Route::post('/profile/transaction-pin', [TransactionPinController::class, 'store'])->middleware('throttle:5,10')->name('profile.transaction-pin.store');
    Route::get('/api-access', [ApiAccessController::class, 'index'])->name('api.access');
    Route::post('/api-access', [ApiAccessController::class, 'store'])->middleware(['throttle:5,1','transaction.pin'])->name('api.access.store');
    Route::delete('/api-access/{token}', [ApiAccessController::class, 'destroy'])->whereNumber('token')->middleware(['throttle:10,1','transaction.pin'])->name('api.access.destroy');
    Route::get('/transactions', [UserTransactionController::class, 'index'])->name('transactions.index');
    Route::get('/wallet/fund', [WalletFundingController::class, 'index'])->name('wallet.fund');
    Route::get('/send-money', fn () => Inertia::render('SendMoney'))->name('send-money.index');
    Route::get('/withdraw', fn () => Inertia::render('Withdraw'))->name('withdraw.index');
    Route::get('/analytics', [FinancialAnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/analytics/export', [FinancialAnalyticsController::class, 'export'])->middleware('throttle:10,1')->name('analytics.export');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/realtime/snapshot', [RealtimeController::class, 'snapshot'])->middleware('throttle:120,1')->name('realtime.snapshot');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->middleware('throttle:30,1')->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->middleware('throttle:60,1')->name('notifications.read');
    Route::post('/profile/password', [UpdatePasswordController::class, 'store'])->middleware('throttle:5,10')->name('profile.password.update');
    Route::get('/help', [HelpCenterController::class, 'index'])->name('help.index');
    Route::get('/help/articles/{article:slug}', [HelpCenterController::class, 'show'])->name('help.article');
    Route::post('/help/articles/{article:slug}/feedback', [HelpCenterController::class, 'feedback'])->middleware('throttle:20,1')->name('help.feedback');
    Route::post('/help/assistant', [HelpCenterController::class, 'ask'])->middleware('throttle:20,1')->name('help.assistant');
    Route::get('/support', [SupportTicketController::class, 'index'])->name('support.index');
    Route::post('/support', [SupportTicketController::class, 'store'])->middleware('throttle:10,1')->name('support.store');
    Route::get('/support/{ticket}', [SupportTicketController::class, 'show'])->whereNumber('ticket')->name('support.show');
    Route::post('/support/{ticket}/reply', [SupportTicketController::class, 'reply'])->whereNumber('ticket')->middleware('throttle:20,1')->name('support.reply');
    Route::patch('/support/{ticket}/status', [SupportTicketController::class, 'updateStatus'])->whereNumber('ticket')->middleware('throttle:30,1')->name('support.status');

    Route::prefix('admin')->middleware(['role:ADMIN,STAFF,SUPPORT', 'verified'])->group(function (): void {
        Route::get('/audit-events', [AuditEventController::class, 'index'])->middleware('permission:audit.view')->name('admin.audit-events.index');
        Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view')->name('admin.users.index');
        Route::get('/kyc', [AdminKycController::class, 'index'])->middleware('permission:users.verify')->name('admin.kyc.index');
        Route::get('/profile-change-requests', [AdminProfileChangeRequestController::class, 'index'])->middleware('permission:users.verify')->name('admin.profile-change-requests.index');
        Route::get('/profile-change-requests/{profileChangeRequest}/documents/{index}', [AdminProfileChangeRequestController::class, 'document'])->whereNumber('profileChangeRequest')->whereNumber('index')->middleware('permission:users.verify')->name('admin.profile-change-requests.document');
        Route::post('/profile-change-requests/{profileChangeRequest}/review', [AdminProfileChangeRequestController::class, 'review'])->whereNumber('profileChangeRequest')->middleware('permission:users.verify')->name('admin.profile-change-requests.review');
        Route::post('/users/{user}/kyc/review', [AdminKycController::class, 'review'])->whereNumber('user')->middleware('permission:users.verify')->name('admin.users.kyc.review');
        Route::get('/users/{user}/kyc/document', [AdminKycController::class, 'document'])->whereNumber('user')->middleware('permission:users.verify')->name('admin.users.kyc.document');
        Route::patch('/users/{user}', [UserController::class, 'update'])->whereNumber('user')->middleware('permission:users.manage')->name('admin.users.update');
        Route::post('/users/{user}/verify', [UserController::class, 'verify'])->whereNumber('user')->middleware('permission:users.verify')->name('admin.users.verify');
        Route::post('/users/{user}/fund', [UserController::class, 'fund'])->whereNumber('user')->middleware('permission:users.fund')->name('admin.users.fund');
        Route::post('/users/{user}/debit', [UserController::class, 'debit'])->whereNumber('user')->middleware('permission:users.fund')->name('admin.users.debit');
        Route::post('/users/{user}/freeze', [UserController::class, 'freeze'])->whereNumber('user')->middleware('permission:users.security.manage')->name('admin.users.freeze');
        Route::put('/users/{user}/permissions', [UserController::class, 'permissions'])->whereNumber('user')->middleware('permission:users.manage')->name('admin.users.permissions');
        Route::post('/users/{user}/wallet-status', [UserController::class, 'walletStatus'])->whereNumber('user')->middleware('permission:users.fund')->name('admin.users.wallet-status');
        Route::get('/health', SystemHealthController::class)->middleware('permission:system.view')->name('admin.health');
        Route::get('/platform-controls', [PlatformControlController::class, 'index'])->middleware('permission:system.manage')->name('admin.platform-controls.index');
        Route::put('/platform-controls', [PlatformControlController::class, 'update'])->middleware(['permission:system.manage','throttle:20,1'])->name('admin.platform-controls.update');
        Route::get('/settings', [SystemSettingsController::class, 'index'])->middleware('permission:system.manage')->name('admin.settings.index');
        Route::put('/settings', [SystemSettingsController::class, 'update'])->middleware(['permission:system.manage', 'throttle:20,1'])->name('admin.settings.update');
        Route::post('/settings/asset', [SystemSettingsController::class, 'upload'])->middleware(['permission:system.manage', 'throttle:20,1'])->name('admin.settings.asset');
        Route::post('/settings/smtp-test', [SystemSettingsController::class, 'testSmtp'])->middleware(['permission:system.manage', 'throttle:5,10'])->name('admin.settings.smtp-test');
        Route::post('/settings/smtp-health', [SystemSettingsController::class, 'smtpHealth'])->middleware(['permission:system.manage', 'throttle:5,10'])->name('admin.settings.smtp-health');
        Route::get('/maintenance/backup', [SystemMaintenanceController::class, 'backup'])->middleware(['permission:system.manage','throttle:2,10'])->name('admin.maintenance.backup');
        Route::post('/maintenance/restore', [SystemMaintenanceController::class, 'restore'])->middleware(['permission:system.manage','throttle:2,10'])->name('admin.maintenance.restore');
        Route::post('/maintenance/cache-clear', [SystemMaintenanceController::class, 'clearCache'])->middleware(['permission:system.manage','throttle:5,10'])->name('admin.maintenance.cache-clear');
        Route::get('/security-events', [SecurityEventController::class, 'index'])->middleware('permission:security.view')->name('admin.security-events.index');
        Route::get('/help/unanswered', [HelpCenterController::class, 'unanswered'])->middleware('permission:help.manage')->name('admin.help.unanswered');

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
        // Self-service provider engine
        Route::get('/providers/{provider}/setup', [ProviderController::class, 'wizard'])->whereNumber('provider')->middleware('permission:providers.view')->name('admin.providers.setup');
        Route::post('/providers/{provider}/connections', [ProviderEngineController::class, 'storeConnection'])->whereNumber('provider')->middleware('permission:providers.manage')->name('admin.providers.connections.store');
        Route::get('/providers/{provider}/connections', [ProviderEngineController::class, 'connections'])->whereNumber('provider')->middleware('permission:providers.view')->name('admin.providers.connections');
        Route::post('/providers/{provider}/test-connection', [ProviderEngineController::class, 'testConnection'])->whereNumber('provider')->middleware('permission:providers.manage')->name('admin.providers.test-connection');
        Route::get('/providers/{provider}/health', [ProviderEngineController::class, 'health'])->whereNumber('provider')->middleware('permission:providers.view')->name('admin.providers.health');
        Route::get('/providers/{provider}/sync-history', [ProviderEngineController::class, 'syncHistory'])->whereNumber('provider')->middleware('permission:providers.view')->name('admin.providers.sync-history');
        Route::get('/providers/{provider}/operation-logs', [ProviderEngineController::class, 'operationLogs'])->whereNumber('provider')->middleware('permission:providers.view')->name('admin.providers.operation-logs');
        Route::get('/provider-auth/schema', [ProviderEngineController::class, 'authSchema'])->middleware('permission:providers.view')->name('admin.provider-auth.schema');
        Route::post('/provider-connections/{connection}/authentication', [ProviderEngineController::class, 'storeAuthentication'])->whereNumber('connection')->middleware('permission:providers.manage')->name('admin.provider-connections.authentication.store');
        Route::get('/provider-connections/{connection}/credentials', [ProviderEngineController::class, 'credentials'])->whereNumber('connection')->middleware('permission:providers.view')->name('admin.provider-connections.credentials');
        Route::post('/provider-connections/{connection}/credentials', [ProviderEngineController::class, 'storeCredential'])->whereNumber('connection')->middleware('permission:providers.manage')->name('admin.provider-connections.credentials.store');

        Route::get('/providers/{provider}/endpoints', [ProviderEngineController::class, 'endpoints'])->whereNumber('provider')->middleware('permission:providers.view')->name('admin.providers.endpoints');
        Route::post('/providers/{provider}/endpoints', [ProviderEngineController::class, 'storeEndpoint'])->whereNumber('provider')->middleware('permission:providers.manage')->name('admin.providers.endpoints.store');
        Route::put('/providers/{provider}/endpoints/{endpoint}', [ProviderEngineController::class, 'endpoint'])->whereNumber(['provider','endpoint'])->middleware('permission:providers.manage')->name('admin.providers.endpoints.update');
        Route::post('/providers/{provider}/endpoints/{endpoint}/test', [ProviderEngineController::class, 'testEndpoint'])->whereNumber(['provider','endpoint'])->middleware('permission:providers.manage')->name('admin.providers.endpoints.test');
        Route::delete('/providers/{provider}/endpoints/{endpoint}', [ProviderEngineController::class, 'destroyEndpoint'])->whereNumber(['provider','endpoint'])->middleware('permission:providers.manage')->name('admin.providers.endpoints.destroy');
        Route::post('/providers/{provider}/discover-services', [ProviderEngineController::class, 'discovery'])->whereNumber('provider')->middleware('permission:providers.manage')->name('admin.providers.discover-services');
        Route::get('/providers/{provider}/provider-services', [ProviderEngineController::class, 'services'])->whereNumber('provider')->middleware('permission:providers.view')->name('admin.providers.provider-services');
        Route::get('/providers/{provider}/mappings', [ProviderEngineController::class, 'providerMappings'])->whereNumber('provider')->middleware('permission:providers.view');
        Route::get('/catalogue/products', [ProviderEngineController::class, 'catalogueProducts'])->middleware('permission:catalogue.view');
        Route::get('/pricing/rules', [PricingRoutingController::class, 'priceRules'])->middleware('permission:catalogue.view');
        Route::post('/pricing/rules', [PricingRoutingController::class, 'storePriceRule'])->middleware('permission:catalogue.manage');
        Route::get('/routing/rules', [PricingRoutingController::class, 'routingRules'])->middleware('permission:providers.view');
        Route::post('/routing/rules', [PricingRoutingController::class, 'storeRoutingRule'])->middleware('permission:providers.manage');
        Route::put('/routing/rules/{rule}', [PricingRoutingController::class, 'updateRoutingRule'])->whereNumber('rule')->middleware('permission:providers.manage');
        Route::delete('/routing/rules/{rule}', [PricingRoutingController::class, 'deleteRoutingRule'])->whereNumber('rule')->middleware('permission:providers.manage');
        Route::post('/providers/{provider}/mappings', [ProviderEngineController::class, 'createMapping'])->whereNumber('provider')->middleware('permission:providers.manage');
        Route::get('/providers/{provider}/provider-services/import-preview', [ProviderEngineController::class, 'importPreview'])->whereNumber('provider')->middleware('permission:providers.view')->name('admin.providers.import-preview');
        Route::post('/providers/{provider}/provider-services/approve', [ProviderEngineController::class, 'approveImport'])->whereNumber('provider')->middleware('permission:providers.manage')->name('admin.providers.approve-import');
        Route::post('/providers/{provider}/provider-services/import', [ProviderEngineController::class, 'importSelected'])->whereNumber('provider')->middleware('permission:providers.manage')->name('admin.providers.provider-services.import');

        Route::get('/catalogue', [CatalogueController::class, 'index'])->middleware('permission:catalogue.view')->name('admin.catalogue.index');
        Route::post('/catalogue/categories', [CatalogueController::class, 'storeCategory'])->middleware('permission:catalogue.manage')->name('admin.catalogue.categories.store');
        Route::post('/catalogue/services', [CatalogueController::class, 'storeService'])->middleware('permission:catalogue.manage')->name('admin.catalogue.services.store');
        Route::post('/catalogue/services/generate-icons', [CatalogueController::class, 'generateServiceIcons'])->middleware('permission:catalogue.manage')->name('admin.catalogue.services.generate-icons');
        Route::post('/catalogue/services/{service}/icon', [CatalogueController::class, 'uploadServiceIcon'])->whereNumber('service')->middleware('permission:catalogue.manage')->name('admin.catalogue.services.icon');
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
        Route::post('/addons/register', [AddonController::class, 'register'])->middleware('permission:addons.manage')->name('admin.addons.register');
        Route::post('/addons/{addon}/install', [AddonController::class, 'install'])->middleware('permission:addons.manage')->name('admin.addons.install');
        Route::post('/addons/{addon}/update', [AddonController::class, 'update'])->middleware('permission:addons.manage')->name('admin.addons.update');
        Route::post('/addons/{addon}/activate', [AddonController::class, 'activate'])->middleware('permission:addons.manage')->name('admin.addons.activate');
        Route::post('/addons/{addon}/disable', [AddonController::class, 'disable'])->middleware('permission:addons.manage')->name('admin.addons.disable');
        Route::post('/addons/{addon}/archive', [AddonController::class, 'archive'])->middleware('permission:addons.manage')->name('admin.addons.archive');
        Route::post('/addons/{addon}/uninstall', [AddonController::class, 'uninstall'])->middleware('permission:addons.manage')->name('admin.addons.uninstall');
    });
});
