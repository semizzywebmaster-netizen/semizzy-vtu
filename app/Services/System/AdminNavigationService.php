<?php

namespace App\Services\System;

use App\Models\Addon;
use App\Models\User;
use Illuminate\Support\Arr;

class AdminNavigationService
{
    public function for(User $user): array
    {
        if (! in_array($user->role, ['ADMIN', 'STAFF', 'SUPPORT'], true)) {
            return [];
        }

        $permissions = config('semizzy.role_permissions.' . $user->role, []);

        $core = [
            ['id' => 'dashboard', 'label' => 'Dashboard', 'url' => '/dashboard', 'icon' => 'home', 'section' => 'core', 'order' => 10],
            ['id' => 'users', 'label' => 'Users & Staff', 'url' => '/admin/users', 'icon' => 'users', 'section' => 'core', 'permission' => 'users.view', 'roles' => ['ADMIN'], 'order' => 20],
            ['id' => 'addons', 'label' => 'Addons', 'url' => '/admin/addons', 'icon' => 'puzzle', 'section' => 'core', 'permission' => 'addons.view', 'roles' => ['ADMIN'], 'order' => 30],
            ['id' => 'providers', 'label' => 'Providers', 'url' => '/admin/providers', 'icon' => 'server', 'section' => 'core', 'permission' => 'providers.view', 'order' => 40],
            ['id' => 'catalogue', 'label' => 'Catalogue', 'url' => '/admin/catalogue', 'icon' => 'catalogue', 'section' => 'core', 'permission' => 'catalogue.view', 'order' => 50],
            ['id' => 'notifications', 'label' => 'Notifications', 'url' => '/notifications', 'icon' => 'bell', 'section' => 'core', 'order' => 60],
            ['id' => 'security', 'label' => 'Security', 'url' => '/admin/security-events', 'icon' => 'shield', 'section' => 'core', 'permission' => 'security.view', 'order' => 70],
            ['id' => 'support', 'label' => 'Support', 'url' => '/support', 'icon' => 'support', 'section' => 'core', 'roles' => ['ADMIN', 'STAFF', 'SUPPORT'], 'order' => 80],
            ['id' => 'system', 'label' => 'System Health', 'url' => '/admin/health', 'icon' => 'settings', 'section' => 'core', 'permission' => 'system.view', 'order' => 90],
            ['id' => 'settings', 'label' => 'System Settings', 'url' => '/admin/settings', 'icon' => 'settings', 'section' => 'core', 'permission' => 'system.manage', 'order' => 100],
            ['id' => 'smtp', 'label' => 'Email & SMTP', 'url' => '/admin/settings#smtp', 'icon' => 'bell', 'section' => 'core', 'permission' => 'system.manage', 'order' => 105],
            ['id' => 'maintenance', 'label' => 'Backup & Maintenance', 'url' => '/admin/settings#maintenance', 'icon' => 'settings', 'section' => 'core', 'permission' => 'system.manage', 'order' => 106],
            ['id' => 'platform-controls', 'label' => 'Platform Controls', 'url' => '/admin/platform-controls', 'icon' => 'settings', 'section' => 'core', 'permission' => 'system.manage', 'order' => 107],
            ['id' => 'communications', 'label' => 'Communication Center', 'url' => '/admin/communications', 'icon' => 'bell', 'section' => 'core', 'permission' => 'communications.manage', 'roles' => ['ADMIN'], 'order' => 108],
            ['id' => 'kyc', 'label' => 'KYC Verification', 'url' => '/admin/kyc', 'icon' => 'shield', 'section' => 'core', 'permission' => 'users.verify', 'roles' => ['ADMIN'], 'order' => 109],
            ['id' => 'profile-change-requests', 'label' => 'Profile Change Requests', 'url' => '/admin/profile-change-requests', 'icon' => 'users', 'section' => 'core', 'permission' => 'users.verify', 'roles' => ['ADMIN'], 'order' => 109],
            ['id' => 'pricing', 'label' => 'Pricing Rules', 'url' => '/admin/pricing', 'icon' => 'catalogue', 'section' => 'core', 'permission' => 'catalogue.view', 'order' => 109],
            ['id' => 'routing', 'label' => 'Provider Routing', 'url' => '/admin/routing', 'icon' => 'server', 'section' => 'core', 'permission' => 'providers.view', 'order' => 109],
            ['id' => 'help-unanswered', 'label' => 'Help Unanswered', 'url' => '/admin/help/unanswered', 'icon' => 'support', 'section' => 'core', 'permission' => 'help.manage', 'roles' => ['ADMIN'], 'order' => 109],
            ['id' => 'audit', 'label' => 'Audit Events', 'url' => '/admin/audit-events', 'icon' => 'audit', 'section' => 'core', 'permission' => 'audit.view', 'order' => 110],
            ['id' => 'profile', 'label' => 'Profile', 'url' => '/profile', 'icon' => 'profile', 'section' => 'account', 'order' => 1000],
        ];

        $items = array_values(array_filter($core, fn (array $item) => $this->visible($item, $user, $permissions)));

        $addons = Addon::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['identifier', 'name', 'navigation']);

        // The VTU addon exposes several operational screens. Keep these visible
        // even when an older stored addon manifest only contains the parent link.
        if (in_array('vtu.view', $permissions, true)
            && $addons->contains(fn (Addon $addon) => $addon->identifier === 'vtu.digital-services')) {
            $vtuItems = [
                ['id'=>'vtu-control','label'=>'VTU Control Center','url'=>'/admin/vtu','icon'=>'server','section'=>'addons','permission'=>'vtu.view','order'=>10],
                ['id'=>'vtu-services','label'=>'VTU Services','url'=>'/admin/vtu/services','icon'=>'catalogue','section'=>'addons','permission'=>'vtu.services.manage','order'=>11],
                ['id'=>'vtu-products','label'=>'VTU Products','url'=>'/admin/vtu/products','icon'=>'catalogue','section'=>'addons','permission'=>'vtu.products.manage','order'=>12],
                ['id'=>'vtu-mappings','label'=>'VTU Mappings','url'=>'/admin/vtu/mappings','icon'=>'catalogue','section'=>'addons','permission'=>'vtu.mappings.manage','order'=>13],
                ['id'=>'vtu-transactions','label'=>'VTU Transactions','url'=>'/admin/vtu/transactions','icon'=>'audit','section'=>'addons','permission'=>'vtu.transactions.view','order'=>14],
                ['id'=>'vtu-bulk','label'=>'VTU Bulk Operations','url'=>'/admin/vtu/bulk','icon'=>'settings','section'=>'addons','permission'=>'vtu.bulk.manage','order'=>15],
            ];
            foreach ($vtuItems as $item) {
                if ($this->visible($item, $user, $permissions)) {
                    $items[] = $item + ['addon'=>'vtu.digital-services','addonName'=>'VTU & Digital Services'];
                }
            }
        }

        foreach ($addons as $addon) {
            foreach ($this->normalizeAddonNavigation($addon->navigation) as $index => $item) {
                if (! is_array($item) || empty($item['label']) || empty($item['url'])) {
                    continue;
                }

                $item['id'] = 'addon:' . $addon->identifier . ':' . ($item['id'] ?? $index);
                $item['section'] = $item['section'] ?? 'addons';
                $item['order'] = (int) ($item['order'] ?? 100);
                $item['addon'] = $addon->identifier;
                $item['addonName'] = $addon->name;

                if ($this->visible($item, $user, $permissions)) {
                    $items[] = $item;
                }
            }
        }

        usort($items, function (array $a, array $b): int {
            $sectionOrder = ['core' => 10, 'addons' => 20, 'account' => 30];
            return [$sectionOrder[$a['section']] ?? 99, (int) ($a['order'] ?? 100), $a['label']]
                <=> [$sectionOrder[$b['section']] ?? 99, (int) ($b['order'] ?? 100), $b['label']];
        });

        return array_map(static fn (array $item): array => Arr::only($item, [
            'id', 'label', 'url', 'icon', 'section', 'order', 'addon', 'addonName',
        ]), $items);
    }

    private function visible(array $item, User $user, array $permissions): bool
    {
        if (! empty($item['roles']) && ! in_array($user->role, (array) $item['roles'], true)) {
            return false;
        }

        if (! empty($item['permission']) && ! in_array($item['permission'], $permissions, true)) {
            return false;
        }

        return true;
    }

    private function normalizeAddonNavigation(mixed $navigation): array
    {
        if (! is_array($navigation)) {
            return [];
        }

        $items = isset($navigation['items']) && is_array($navigation['items'])
            ? $navigation['items']
            : $navigation;

        return array_values(array_filter($items, 'is_array'));
    }
}
