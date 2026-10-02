<?php

return [
    'finance_enabled' => filter_var(env('FINANCE_ENABLED', false), FILTER_VALIDATE_BOOL),
    'admin_login_path' => env('ADMIN_LOGIN_PATH', 'admin/login'),
    'role_permissions' => [
        'ADMIN' => ['system.view', 'security.view', 'providers.view', 'providers.manage', 'catalogue.view', 'catalogue.manage', 'addons.view', 'addons.manage'],
        'STAFF' => ['system.view', 'providers.view', 'catalogue.view'],
        'SUPPORT' => [],
        'USER' => [],
    ],
];
