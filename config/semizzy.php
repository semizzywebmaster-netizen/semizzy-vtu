<?php

return [
    'finance_enabled' => filter_var(env('FINANCE_ENABLED', false), FILTER_VALIDATE_BOOL),
    'admin_login_path' => env('ADMIN_LOGIN_PATH', 'admin/login'),
    'testing_installed' => false,
    'user_tiers' => [
        1 => ['name' => 'Tier 1', 'daily_limit_minor' => '5000000', 'balance_limit_minor' => '30000000', 'upgrade_label' => 'Upgrade to Tier 2'],
        2 => ['name' => 'Tier 2', 'daily_limit_minor' => '20000000', 'balance_limit_minor' => '50000000', 'upgrade_label' => 'Upgrade to Tier 3'],
        3 => ['name' => 'Tier 3', 'daily_limit_minor' => '500000000', 'balance_limit_minor' => null, 'upgrade_label' => null],
    ],
    'role_permissions' => [
        'ADMIN' => ['vtu.view', 'vtu.services.manage', 'vtu.products.manage', 'vtu.providers.manage', 'vtu.mappings.manage', 'vtu.transactions.view', 'vtu.transactions.manage', 'vtu.bulk.manage', 'vtu.requery', 'vtu.refunds.manage', 'vtu.settings.manage', 'system.view', 'system.manage', 'security.view', 'audit.view', 'providers.view', 'providers.manage', 'catalogue.view', 'catalogue.manage', 'addons.view', 'addons.manage', 'users.view', 'users.manage', 'users.verify', 'users.fund', 'users.tier.manage'],
        'STAFF' => ['vtu.view', 'vtu.transactions.view', 'vtu.requery', 'system.view', 'providers.view', 'catalogue.view'],
        'SUPPORT' => [],
        'USER' => ['vtu.view'],
    ],
];
