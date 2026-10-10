<?php

return [
    'identifier' => 'smm.services',
    'name' => 'SMM Services',
    'version' => '0.1.0',
    'autoload_namespace' => 'Semizzy\\Addons\\Smm',
    'description' => 'Social media marketing services catalogue and order foundation with Core Provider Engine integration.',
    'compatibility' => '>=2.0.0',
    'dependencies' => [],
    'permissions' => [
        'smm.view',
        'smm.orders.manage',
        'smm.services.manage',
        'smm.providers.manage',
        'smm.transactions.view',
        'smm.requery',
        'smm.cancel',
        'smm.settings.manage',
    ],
    'role_permissions' => [
        'ADMIN' => ['smm.view','smm.orders.manage','smm.services.manage','smm.providers.manage','smm.transactions.view','smm.requery','smm.cancel','smm.settings.manage'],
        'STAFF' => ['smm.view','smm.orders.manage','smm.services.manage','smm.transactions.view','smm.requery'],
        'SUPPORT' => ['smm.view','smm.transactions.view','smm.requery'],
        'USER' => ['smm.view','smm.transactions.view'],
    ],
    'navigation' => [
        ['id'=>'smm-services','label'=>'SMM Services','url'=>'/smm','icon'=>'share','permission'=>'smm.view','section'=>'services','order'=>140],
    ],
    'admin_navigation' => [
        ['id'=>'admin-smm-services','label'=>'SMM Services','url'=>'/admin/smm','icon'=>'share','permission'=>'smm.view','section'=>'addons','order'=>140],
    ],
    'settings' => [
        ['key'=>'enabled','type'=>'boolean','default'=>true],
        ['key'=>'default_currency','type'=>'string','default'=>'NGN'],
        ['key'=>'min_order_minor','type'=>'integer','default'=>100],
        ['key'=>'max_order_minor','type'=>'integer','default'=>100000000],
    ],
    'migrations' => ['2026_10_07_001500_create_smm_services_tables.php', '2026_10_08_001501_harden_smm_order_wallet_binding.php'],
    'web_route_files' => ['addons/smm.services/routes/web.php','addons/smm.services/routes/admin.php'],
    'api_route_files' => ['addons/smm.services/routes/api.php'],
    'routes' => ['/smm','/admin/smm'],
    'api_routes' => ['/api/v1/smm'],
    'provider_integrations' => ['Core ProviderManager'],
    'provider_capabilities' => ['smm_order','smm_status','smm_requery','smm_cancel'],
    'capabilities' => ['catalogue','provider_mapping','order_idempotency','status_tracking','requery','conditional_cancellation','transaction_pin'],
    'scheduled_tasks' => ['SMM pending-order reconciliation'],
    'events' => ['smm.order.created','smm.order.status.changed','smm.order.completed','smm.order.failed'],
];