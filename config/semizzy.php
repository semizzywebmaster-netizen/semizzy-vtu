<?php

return [
    'finance_enabled' => filter_var(env('FINANCE_ENABLED', false), FILTER_VALIDATE_BOOL),
    'admin_login_path' => env('ADMIN_LOGIN_PATH', 'admin/login'),
    'testing_installed' => false,
    'user_tiers' => [
        1 => ['name' => 'Tier 1', 'daily_limit_minor' => '5000000', 'balance_limit_minor' => '30000000', 'upgrade_label' => 'Upgrade to Tier 2', 'type' => 'personal', 'requirements' => ['basic registration']],
        2 => ['name' => 'Tier 2', 'daily_limit_minor' => '20000000', 'balance_limit_minor' => '50000000', 'upgrade_label' => 'Upgrade to Tier 3', 'type' => 'personal', 'requirements' => ['identity verification']],
        3 => ['name' => 'Tier 3', 'daily_limit_minor' => '500000000', 'balance_limit_minor' => '1000000000', 'upgrade_label' => 'Upgrade to Tier 4 Merchant', 'type' => 'personal', 'requirements' => ['enhanced verification']],
        4 => ['name' => 'Merchant', 'daily_limit_minor' => null, 'balance_limit_minor' => null, 'upgrade_label' => null, 'type' => 'merchant', 'requirements' => ['business/company details', 'merchant approval']],
    ],
    'help' => [
        'ai' => [
            'enabled' => filter_var(env('HELP_AI_ENABLED', false), FILTER_VALIDATE_BOOL),
            'endpoint' => env('HELP_AI_ENDPOINT'),
            'api_key' => env('HELP_AI_API_KEY'),
            'model' => env('HELP_AI_MODEL', 'help-assistant'),
        ],
    ],
    'core_heads_up' => [
        'future_addons' => [
            'whatsapp_transaction_bot',
            'biometric_otp',
            'payment_channels',
            'advanced_referrals',
            'pos',
            'p2p',
            'chat',
        ],
        'verification_channels' => ['email', 'sms', 'whatsapp'],
        'realtime' => ['polling', 'webhooks', 'broadcast_when_supported'],
        'communications' => ['web_push', 'email', 'sms', 'whatsapp'],
        'analytics' => ['wallet', 'transactions', 'spending', 'funding', 'fees', 'refunds'],
    ],
    'username_policy' => [
        'min_length' => 3,
        'max_length' => 40,
        'pattern' => '/^[a-zA-Z0-9._]+$/',
        'reserved' => ['admin','administrator','support','staff','system','official','government','police','cbn','bank','semizzy','semizzywebmaster'],
        'protected_terms' => ['admin','administrator','support','staff','official','government','police','scam','fraud','sex'],
    ],
    'role_permissions' => [
        'ADMIN' => ['vtu.view', 'vtu.services.manage', 'vtu.products.manage', 'vtu.providers.manage', 'vtu.mappings.manage', 'vtu.transactions.view', 'vtu.transactions.manage', 'vtu.bulk.manage', 'vtu.requery', 'vtu.refunds.manage', 'vtu.settings.manage', 'system.view', 'system.manage', 'security.view', 'audit.view', 'providers.view', 'providers.manage', 'catalogue.view', 'catalogue.manage', 'addons.view', 'addons.manage', 'users.view', 'users.manage', 'users.verify', 'users.fund', 'users.tier.manage', 'users.security.manage', 'users.merchant.manage', 'communications.manage', 'help.manage', 'analytics.view'],
        'STAFF' => ['vtu.view', 'vtu.transactions.view', 'vtu.requery', 'system.view', 'providers.view', 'catalogue.view'],
        'SUPPORT' => [],
        'USER' => ['vtu.view'],
    ],
];
