<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use App\Models\ApiProvider;
use App\Models\Service;
use App\Models\ServiceProduct;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $isAdmin = $user->hasRole('ADMIN');
        $canViewProviders = in_array($user->role, ['ADMIN', 'STAFF'], true);
        $canViewCatalogue = in_array($user->role, ['ADMIN', 'STAFF'], true);
        $canManageSupport = $user->hasRole(['ADMIN', 'STAFF', 'SUPPORT']);

        $metrics = [];

        if ($isAdmin) {
            $metrics = [
                ['label' => 'User accounts', 'value' => User::query()->count(), 'description' => 'Registered accounts'],
                ['label' => 'Eligible providers', 'value' => ApiProvider::query()->eligibleForNewTransactions()->count(), 'description' => 'Verified, enabled and unpaused'],
                ['label' => 'Active addons', 'value' => Addon::query()->where('status', 'active')->count(), 'description' => 'Currently active core extensions'],
                ['label' => 'Enabled products', 'value' => ServiceProduct::query()->where('enabled', true)->count(), 'description' => 'Catalogue products enabled in core'],
                ['label' => 'Open support tickets', 'value' => SupportTicket::query()->whereIn('status', ['open', 'pending'])->count(), 'description' => 'Tickets awaiting attention'],
            ];
        } else {
            if ($canViewProviders) {
                $metrics[] = ['label' => 'Eligible providers', 'value' => ApiProvider::query()->eligibleForNewTransactions()->count(), 'description' => 'Verified, enabled and unpaused'];
            }

            if ($canViewCatalogue) {
                $metrics[] = ['label' => 'Enabled services', 'value' => Service::query()->where('enabled', true)->count(), 'description' => 'Core catalogue services'];
            }

            if ($canManageSupport) {
                $metrics[] = ['label' => 'Open support tickets', 'value' => SupportTicket::query()->whereIn('status', ['open', 'pending'])->count(), 'description' => 'Tickets awaiting attention'];
            } else {
                $metrics[] = ['label' => 'My support tickets', 'value' => SupportTicket::query()->where('user_id', $user->id)->count(), 'description' => 'Tickets submitted by your account'];
            }

            $metrics[] = ['label' => 'Unread notifications', 'value' => $user->unreadNotifications()->count(), 'description' => 'Updates waiting for you'];
        }

        $quickLinks = [];

        if ($isAdmin) {
            $quickLinks = [
                ['label' => 'Users & staff', 'url' => '/admin/users'],
                ['label' => 'API providers', 'url' => '/admin/providers'],
                ['label' => 'Service catalogue', 'url' => '/admin/catalogue'],
                ['label' => 'Addon manager', 'url' => '/admin/addons'],
                ['label' => 'System health', 'url' => '/admin/health'],
                ['label' => 'Audit events', 'url' => '/admin/audit-events'],
                ['label' => 'Security events', 'url' => '/admin/security-events'],
                ['label' => 'Support desk', 'url' => '/support'],
            ];
        } elseif ($user->hasRole('STAFF')) {
            $quickLinks = [
                ['label' => 'API providers', 'url' => '/admin/providers'],
                ['label' => 'Service catalogue', 'url' => '/admin/catalogue'],
                ['label' => 'Support desk', 'url' => '/support'],
            ];
        } elseif ($user->hasRole('SUPPORT')) {
            $quickLinks = [['label' => 'Support desk', 'url' => '/support']];
        } else {
            $quickLinks = [
                ['label' => 'Notifications', 'url' => '/notifications'],
                ['label' => 'Contact support', 'url' => '/support'],
                ['label' => 'My profile', 'url' => '/profile'],
            ];
        }

        return Inertia::render('Dashboard', [
            'role' => $user->role,
            'metrics' => $metrics,
            'quickLinks' => $quickLinks,
        ]);
    }
}
