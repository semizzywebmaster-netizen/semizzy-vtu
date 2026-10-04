<?php

return [
    'finance_enabled' => filter_var(env('FINANCE_ENABLED', false), FILTER_VALIDATE_BOOL),
    'admin_login_path' => env('ADMIN_LOGIN_PATH', 'admin/login'),
    'role_permissions' => [
        'ADMIN' => ['vtu.view', 'vtu.services.manage', 'vtu.products.manage', 'vtu.providers.manage', 'vtu.mappings.manage', 'vtu.transactions.view', 'vtu.transactions.manage', 'vtu.bulk.manage', 'vtu.requery', 'vtu.refunds.manage', 'vtu.settings.manage', 'system.view', 'system.manage', 'security.view', 'audit.view', 'providers.view', 'providers.manage', 'catalogue.view', 'catalogue.manage', 'addons.view', 'addons.manage', 'users.view', 'users.manage'],
        'STAFF' => ['vtu.view', 'vtu.transactions.view', 'vtu.requery', 'system.view', 'providers.view', 'catalogue.view'],
        'SUPPORT' => [],
        'USER' => [],
    ],
];
