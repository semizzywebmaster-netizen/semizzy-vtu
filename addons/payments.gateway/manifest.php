<?php

return [
    'identifier' => 'payments.gateway',
    'name' => 'Payments & Funding',
    'version' => '1.0.0',
    'compatibility' => '>=2.0.0',
    'dependencies' => [],
    'role_permissions' => [
        'ADMIN' => ['payments.view', 'payments.manage', 'payments.providers.manage', 'payments.refunds.manage', 'payments.webhooks.manage', 'payments.settings.manage'],
        'STAFF' => ['payments.view', 'payments.refunds.manage'],
        'SUPPORT' => ['payments.view'],
        'USER' => ['payments.view', 'payments.create'],
    ],
    'permissions' => [
        'payments.view',
        'payments.create',
        'payments.manage',
        'payments.providers.manage',
        'payments.refunds.manage',
        'payments.webhooks.manage',
        'payments.settings.manage',
    ],
    'navigation' => [
        [
            'id' => 'payments',
            'label' => 'Payments & Funding',
            'url' => '/admin/payments',
            'icon' => 'wallet',
            'permission' => 'payments.view',
            'section' => 'addons',
            'order' => 30,
        ],
    ],
    'settings' => [
        ['key' => 'default_currency', 'type' => 'string', 'default' => 'NGN'],
        ['key' => 'payment_expiry_minutes', 'type' => 'integer', 'default' => 30],
        ['key' => 'auto_credit_verified_payments', 'type' => 'boolean', 'default' => true],
        ['key' => 'require_webhook_signature', 'type' => 'boolean', 'default' => true],
    ],
    'migrations' => [
        '2026_10_06_000300_create_payment_intents.php',
        '2026_10_06_000301_create_payment_webhook_events.php',
    ],
    'web_route_files' => [
        'addons/payments.gateway/routes/web.php',
        'addons/payments.gateway/routes/admin.php',
    ],
    'api_route_files' => [
        'addons/payments.gateway/routes/api.php',
    ],
    'routes' => ['/payments'],
    'api_routes' => ['/api/v1/payments'],
    'services' => [
        'payment intent lifecycle',
        'wallet funding',
        'provider checkout integration',
        'webhook verification and idempotency',
        'refund and reconciliation',
    ],
    'provider_integrations' => ['Core ProviderManager'],
    'scheduled_tasks' => ['payment expiry/reconciliation maintenance'],
    'events' => [
        'payment.created',
        'payment.succeeded',
        'payment.failed',
        'payment.refunded',
    ],
];
