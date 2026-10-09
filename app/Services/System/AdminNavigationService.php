<?php

namespace App\Services\System;

use App\Models\Addon;
use App\Models\User;
use App\Models\SystemSetting;
use Illuminate\Support\Arr;

class AdminNavigationService
{
    public function for(User $user): array
    {
        if (! in_array($user->role, ['ADMIN', 'STAFF', 'SUPPORT'], true)) {
            return [];
        }

        $allAddons = Addon::query()->get(['identifier', 'name', 'navigation', 'permissions', 'manifest', 'status']);
        $activeAddons = $allAddons->where('status', 'active');
        $knownAddonPermissions = $allAddons->flatMap(fn (Addon $addon) => is_array($addon->permissions) ? $addon->permissions : [])->unique()->values()->all();
        $activeAddonPermissions = $activeAddons->flatMap(function (Addon $addon) use ($user): array {
            $rolePermissions = data_get($addon->manifest, 'role_permissions.' . $user->role, []);
            return is_array($rolePermissions) ? $rolePermissions : [];
        })->unique()->values()->all();

        $permissions = array_values(array_unique(array_merge(
            array_filter(
                config('semizzy.role_permissions.' . $user->role, []),
                fn (string $permission): bool => ! in_array($permission, $knownAddonPermissions, true)
                    || in_array($permission, $activeAddonPermissions, true),
            ),
            $activeAddonPermissions,
        )));
        foreach ($user->permissionOverrides()->get(['permission', 'allowed']) as $override) {
            if (in_array($override->permission, $knownAddonPermissions, true)
                && ! in_array($override->permission, $activeAddonPermissions, true)) {
                continue;
            }
            $permissions = array_values(array_diff($permissions, [$override->permission]));
            if ((bool) $override->allowed) {
                $permissions[] = $override->permission;
            }
        }

        $featureSettings = SystemSetting::query()
            ->whereIn('key', ['vtu_enabled', 'api_enabled'])
            ->pluck('value', 'key')
            ->map(fn ($value) => filter_var($value, FILTER_VALIDATE_BOOL))
            ->all();

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
            ['id' => 'runbooks', 'label' => 'Operational Runbooks', 'url' => '/admin/runbooks', 'icon' => 'support', 'section' => 'core', 'permission' => 'system.view', 'order' => 95],
            ['id' => 'feature-rollouts', 'label' => 'Safe Rollout Controls', 'url' => '/admin/feature-rollouts', 'icon' => 'settings', 'section' => 'core', 'permission' => 'system.manage', 'roles' => ['ADMIN'], 'order' => 96],
            ['id' => 'settings', 'label' => 'System Settings', 'url' => '/admin/settings', 'icon' => 'settings', 'section' => 'core', 'permission' => 'system.manage', 'order' => 100],
            ['id' => 'smtp', 'label' => 'Email & SMTP', 'url' => '/admin/settings#smtp', 'icon' => 'bell', 'section' => 'core', 'permission' => 'system.manage', 'order' => 105],
            ['id' => 'maintenance', 'label' => 'Backup & Maintenance', 'url' => '/admin/settings#maintenance', 'icon' => 'settings', 'section' => 'core', 'permission' => 'system.manage', 'order' => 106],
            ['id' => 'platform-controls', 'label' => 'Platform Controls', 'url' => '/admin/platform-controls', 'icon' => 'settings', 'section' => 'core', 'permission' => 'system.manage', 'order' => 107],
            ['id' => 'communications', 'label' => 'Communication Center', 'url' => '/admin/communications', 'icon' => 'bell', 'section' => 'core', 'permission' => 'communications.manage', 'roles' => ['ADMIN'], 'order' => 108],
            ['id' => 'kyc', 'label' => 'KYC Verification', 'url' => '/admin/kyc', 'icon' => 'shield', 'section' => 'core', 'permission' => 'users.verify', 'roles' => ['ADMIN'], 'order' => 109],
            ['id' => 'profile-change-requests', 'label' => 'Profile Change Requests', 'url' => '/admin/profile-change-requests', 'icon' => 'users', 'section' => 'core', 'permission' => 'users.verify', 'roles' => ['ADMIN'], 'order' => 109],
            ['id' => 'help-unanswered', 'label' => 'Help Unanswered', 'url' => '/admin/help/unanswered', 'icon' => 'support', 'section' => 'core', 'permission' => 'help.manage', 'roles' => ['ADMIN'], 'order' => 109],
            ['id' => 'audit', 'label' => 'Audit Events', 'url' => '/admin/audit-events', 'icon' => 'audit', 'section' => 'core', 'permission' => 'audit.view', 'order' => 110],
            ['id' => 'profile', 'label' => 'Profile', 'url' => '/profile', 'icon' => 'profile', 'section' => 'account', 'order' => 1000],
        ];

        $items = array_values(array_filter($core, fn (array $item) => $this->visible($item, $user, $permissions, $featureSettings)));

        $addons = $activeAddons->sortBy('name');

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
                $requiredFeature = match ($addon->identifier) {
                    'vtu.digital-services' => 'vtu_enabled',
                    default => null,
                };
                if ($requiredFeature !== null && array_key_exists($requiredFeature, $featureSettings) && ! $featureSettings[$requiredFeature]) {
                    continue;
                }

                if ($this->visible($item, $user, $permissions, $featureSettings)) {
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

    private function visible(array $item, User $user, array $permissions, array $featureSettings = []): bool
    {
        if (! empty($item['roles']) && ! in_array($user->role, (array) $item['roles'], true)) {
            return false;
        }

        if (! empty($item['permission']) && ! in_array((string) $item['permission'], $permissions, true)) {
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
