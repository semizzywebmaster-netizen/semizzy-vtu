<?php

return [
    'identifier' => 'crypto-payments.gateway',
    'name' => 'Crypto Payment Gateway',
    'version' => '1.0.0',
    'compatibility' => '>=2.0.0',
    'dependencies' => [],
    'role_permissions' => [
        'ADMIN' => ['crypto.view', 'crypto.manage', 'crypto.providers.manage', 'crypto.webhooks.manage', 'crypto.settings.manage'],
        'STAFF' => ['crypto.view'],
        'SUPPORT' => ['crypto.view'],
        'USER' => ['crypto.view', 'crypto.create'],
    ],
    'permissions' => ['crypto.view','crypto.create','crypto.manage','crypto.providers.manage','crypto.webhooks.manage','crypto.settings.manage'],
    'navigation' => [['id'=>'crypto-payments','label'=>'Crypto Payments','url'=>'/admin/crypto-payments','icon'=>'coins','permission'=>'crypto.view','section'=>'addons','order'=>31]],
    'settings' => [
        ['key'=>'default_fiat_currency','type'=>'string','default'=>'NGN'],
        ['key'=>'payment_expiry_minutes','type'=>'integer','default'=>30],
        ['key'=>'require_webhook_signature','type'=>'boolean','default'=>true],
        ['key'=>'automatic_provider_failover','type'=>'boolean','default'=>true],
        ['key'=>'minimum_confirmations_default','type'=>'integer','default'=>1],
        ['key'=>'funding_fee_percent','type'=>'decimal','default'=>0],
    ],
    'migrations' => [
        '2026_10_08_003000_create_crypto_payment_providers.php',
        '2026_10_08_003001_seed_crypto_payment_providers.php',
        '2026_10_08_003002_create_crypto_payment_transactions.php',
        '2026_10_08_003003_create_crypto_payment_settings.php',
    ],
    'web_route_files' => ['addons/crypto-payments.gateway/routes/admin.php'],
    'api_route_files' => ['addons/crypto-payments.gateway/routes/api.php'],
    'routes' => ['/crypto-payments'],
    'api_routes' => ['/api/v1/crypto-payments'],
    'services' => ['separate crypto payment gateway provider engine','unlimited admin-addable crypto gateway providers','capability-based routing and automatic failover','crypto checkout and invoice processing','payment verification and webhook idempotency foundation','asset and network capability declarations','crypto payout support only where the provider explicitly supports it','no custodial wallet or private-key storage by default','admin-configurable crypto wallet funding surcharge with transaction snapshot'],
    'provider_integrations' => [],
    'scheduled_tasks' => ['crypto payment expiry/reconciliation maintenance'],
    'events' => ['crypto.payment.created','crypto.payment.succeeded','crypto.payment.failed','crypto.payment.provider.failed','crypto.payment.provider.health_changed'],
];
