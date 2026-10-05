<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use App\Models\ApiProvider;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceProduct;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\VtuTransaction;
use App\Models\WalletAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $role = $user->role;
        $permissions = config('semizzy.role_permissions.' . $role, []);
        $isAdmin = $role === 'ADMIN';
        $isStaff = $role === 'STAFF';
        $isSupport = $role === 'SUPPORT';
        $isOperations = $isAdmin || $isStaff || $isSupport;
        $can = static fn (string $permission): bool => in_array($permission, $permissions, true);

        $metrics = [];

        if ($isAdmin) {
            $metrics = [
                ['label' => 'User accounts', 'value' => User::query()->count(), 'description' => 'Registered accounts'],
                ['label' => 'Eligible providers', 'value' => ApiProvider::query()->eligibleForNewTransactions()->count(), 'description' => 'Verified, enabled and unpaused'],
                ['label' => 'Active addons', 'value' => Addon::query()->where('status', 'active')->count(), 'description' => 'Currently active core extensions'],
                ['label' => 'Enabled products', 'value' => ServiceProduct::query()->where('enabled', true)->count(), 'description' => 'Catalogue products enabled in core'],
                ['label' => 'Open support tickets', 'value' => SupportTicket::query()->whereIn('status', ['open', 'pending'])->count(), 'description' => 'Tickets awaiting attention'],
            ];
        } elseif ($isStaff) {
            if ($can('providers.view')) {
                $metrics[] = ['label' => 'Eligible providers', 'value' => ApiProvider::query()->eligibleForNewTransactions()->count(), 'description' => 'Verified, enabled and unpaused'];
            }
            if ($can('catalogue.view')) {
                $metrics[] = ['label' => 'Enabled services', 'value' => Service::query()->where('enabled', true)->count(), 'description' => 'Services currently enabled in catalogue'];
                $metrics[] = ['label' => 'Enabled products', 'value' => ServiceProduct::query()->where('enabled', true)->count(), 'description' => 'Products currently enabled in catalogue'];
            }
            if ($can('vtu.transactions.view')) {
                $metrics[] = ['label' => 'VTU transactions', 'value' => VtuTransaction::query()->count(), 'description' => 'Transactions recorded by the VTU addon'];
            }
            $metrics[] = ['label' => 'Open support tickets', 'value' => SupportTicket::query()->where('status', 'open')->count(), 'description' => 'Customer requests awaiting first response'];
            $metrics[] = ['label' => 'Pending support tickets', 'value' => SupportTicket::query()->where('status', 'pending')->count(), 'description' => 'Requests awaiting follow-up'];
            $metrics[] = ['label' => 'Unread notifications', 'value' => $user->unreadNotifications()->count(), 'description' => 'Operational updates waiting for you'];
        } elseif ($isSupport) {
            $metrics = [
                ['label' => 'Open support tickets', 'value' => SupportTicket::query()->where('status', 'open')->count(), 'description' => 'Customer requests awaiting first response'],
                ['label' => 'Pending support tickets', 'value' => SupportTicket::query()->where('status', 'pending')->count(), 'description' => 'Requests awaiting follow-up'],
                ['label' => 'Unread notifications', 'value' => $user->unreadNotifications()->count(), 'description' => 'Updates waiting for you'],
            ];
        } else {
            $metrics = [
                ['label' => 'My support tickets', 'value' => SupportTicket::query()->where('user_id', $user->id)->count(), 'description' => 'Tickets submitted by your account'],
                ['label' => 'Unread notifications', 'value' => $user->unreadNotifications()->count(), 'description' => 'Updates waiting for you'],
            ];
        }

        $quickLinks = [];
        if ($isAdmin) {
            $quickLinks = [
                ['label' => 'Users & staff', 'url' => '/admin/users'],
                ['label' => 'API providers', 'url' => '/admin/providers'],
                ['label' => 'Service catalogue', 'url' => '/admin/catalogue'],
                ['label' => 'Addon manager', 'url' => '/admin/addons'],
                ['label' => 'System health', 'url' => '/admin/health'],
                ['label' => 'System settings', 'url' => '/admin/settings'],
                ['label' => 'Audit events', 'url' => '/admin/audit-events'],
                ['label' => 'Security events', 'url' => '/admin/security-events'],
                ['label' => 'VTU Control Center', 'url' => '/admin/vtu'],
                ['label' => 'VTU Services', 'url' => '/admin/vtu/services'],
                ['label' => 'VTU Products', 'url' => '/admin/vtu/products'],
                ['label' => 'VTU Providers', 'url' => '/admin/providers'],
                ['label' => 'VTU Mappings', 'url' => '/admin/vtu/mappings'],
                ['label' => 'VTU Transactions', 'url' => '/admin/vtu/transactions'],
                ['label' => 'VTU Bulk Operations', 'url' => '/admin/vtu/bulk'],
                ['label' => 'Support desk', 'url' => '/support'],
            ];
        } elseif ($isStaff) {
            if ($can('users.view')) $quickLinks[] = ['label' => 'Users & staff', 'url' => '/admin/users'];
            if ($can('providers.view')) $quickLinks[] = ['label' => 'API providers', 'url' => '/admin/providers'];
            if ($can('catalogue.view')) $quickLinks[] = ['label' => 'Service catalogue', 'url' => '/admin/catalogue'];
            if ($can('vtu.transactions.view')) $quickLinks[] = ['label' => 'VTU transactions', 'url' => '/admin/vtu/transactions'];
            $quickLinks[] = ['label' => 'Support desk', 'url' => '/support'];
            $quickLinks[] = ['label' => 'Notifications', 'url' => '/notifications'];
            $quickLinks[] = ['label' => 'My profile', 'url' => '/profile'];
        } elseif ($isSupport) {
            $quickLinks = [
                ['label' => 'Support desk', 'url' => '/support'],
                ['label' => 'Notifications', 'url' => '/notifications'],
                ['label' => 'My profile', 'url' => '/profile'],
            ];
        } else {
            $quickLinks = [
                ['label' => 'Notifications', 'url' => '/notifications'],
                ['label' => 'VTU Services', 'url' => '/vtu'],
                ['label' => 'Contact support', 'url' => '/support'],
                ['label' => 'My profile', 'url' => '/profile'],
            ];
        }

        $serviceCategories = [];
        if (!$isOperations) {
            $serviceCategories = ServiceCategory::query()
                ->where('enabled', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->with(['services' => fn ($q) => $q->where('enabled', true)->orderBy('name')])
                ->get()
                ->map(fn (ServiceCategory $category) => [
                    'key' => $category->key,
                    'name' => $category->name,
                    'description' => $category->description,
                    'services' => $category->services->map(fn (Service $service) => [
                        'key' => $service->key,
                        'name' => $service->name,
                        'description' => $service->description,
                        'url' => '/vtu?service=' . urlencode($service->key),
                    ])->values()->all(),
                ])->values()->all();
        }

        $wallet = null;
        $tier = null;
        $tierLimits = [];
        if (!$isOperations) {
            $wallet = WalletAccount::query()->where('user_id', $user->id)->where('status', '!=', 'closed')->first();
            $tierNumber = max(1, min(3, (int) $user->tier));
            $tier = [
                'id' => $tierNumber,
                'name' => config("semizzy.user_tiers.{$tierNumber}.name", 'Tier '.$tierNumber),
            ];
            $tierLimits = collect(config('semizzy.user_tiers', []))->map(fn (array $definition, $key): array => [
                'id' => (int) $key,
                'name' => $definition['name'],
                'dailyLimitMinor' => $definition['daily_limit_minor'],
                'balanceLimitMinor' => $definition['balance_limit_minor'],
                'upgradeLabel' => $definition['upgrade_label'],
            ])->values()->all();
        }

        return Inertia::render('Dashboard', [
            'role' => $role,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
            ],
            'metrics' => $metrics,
            'quickLinks' => $quickLinks,
            'serviceCategories' => $serviceCategories,
            'wallet' => $wallet ? [
                'available_minor' => $wallet->available_minor,
                'held_minor' => $wallet->held_minor,
                'currency' => $wallet->currency,
                'status' => $wallet->status,
            ] : null,
            'tier' => $tier,
            'tierLimits' => $tierLimits,
            'workspace' => [
                'operations' => $isOperations,
                'staff' => $isStaff,
                'supportOpen' => $isOperations ? SupportTicket::query()->where('status', 'open')->count() : 0,
                'supportPending' => $isOperations ? SupportTicket::query()->where('status', 'pending')->count() : 0,
            ],
        ]);
    }
}
