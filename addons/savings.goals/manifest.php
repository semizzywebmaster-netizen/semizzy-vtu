<?php

return [
    'identifier' => 'savings.goals',
    'name' => 'Savings & Wallet Goals',
    'version' => '1.0.0',
    'description' => 'Goal-based and structured savings services built on the SEMIZZY ONE wallet ledger.',
    'compatibility' => '>=2.0.0',
    'dependencies' => [],
    'permissions' => [
        'savings.view',
        'savings.create',
        'savings.manage',
        'savings.withdraw',
        'savings.settings.manage',
    ],
    'role_permissions' => [
        'ADMIN' => [
            'savings.view','savings.create','savings.manage','savings.withdraw','savings.settings.manage',
        ],
        'STAFF' => ['savings.view','savings.manage'],
        'SUPPORT' => ['savings.view'],
        'USER' => ['savings.view','savings.create','savings.withdraw'],
    ],
    'navigation' => [
        [
            'id' => 'savings',
            'label' => 'Savings',
            'url' => '/savings',
            'icon' => 'piggy-bank',
            'permission' => 'savings.view',
            'section' => 'services',
            'order' => 40,
        ],
    ],
    'admin_navigation' => [
        [
            'id' => 'admin-savings',
            'label' => 'Savings',
            'url' => '/admin/savings',
            'icon' => 'piggy-bank',
            'permission' => 'savings.view',
            'section' => 'addons',
            'order' => 40,
        ],
    ],
    'settings' => [
        'enabled' => true,
        'default_currency' => 'NGN',
        'minimum_amount_minor' => 100,
        'maximum_amount_minor' => 1000000000,
        'early_withdrawal_enabled' => true,
    ],
    'migrations' => [
        '2026_10_06_000400_create_savings_plans.php',
        '2026_10_06_000401_create_savings_accounts.php',
        '2026_10_06_000402_create_savings_movements.php',
        '2026_10_06_000403_seed_default_savings_plans.php',
        '2026_10_07_000901_expand_savings_product_lifecycle.php',
        '2026_10_09_130200_harden_savings_movement_retention.php',
    ],
    'web_route_files' => [
        'addons/savings.goals/routes/web.php',
        'addons/savings.goals/routes/admin.php',
    ],
    'api_route_files' => [
        'addons/savings.goals/routes/api.php',
    ],
    'provider_integration' => [
        'type' => 'core',
        'manager' => 'App\\Services\\Providers\\ProviderManager',
        'capabilities' => [],
    ],
    'scheduled_tasks' => [
        'maturity' => 'Process savings maturity and scheduled contributions through the database queue/cPanel cron.',
    ],
    'events' => [
        'savings.created',
        'savings.funded',
        'savings.withdrawn',
        'savings.matured',
    ],
];
