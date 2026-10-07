<?php

return [
    'identifier'=>'sim-hosting',
    'name'=>'SIM Hosting — Data, Airtime & SMS Provider',
    'version'=>'2.0.0',
    'description'=>'Provider integration addon for SIM Hosting APIs that supply Data, Airtime and SMS. It plugs into the Core Provider Engine and is consumed by service addons such as VTU & Digital Services and Bulk SMS.',
    'compatibility'=>'>=2.0.0',
    'dependencies'=>[],
    'permissions'=>[
        'sim_hosting.view',
        'sim_hosting.providers.manage',
        'sim_hosting.settings.manage',
        'sim_hosting.transactions.view'
    ],
    'role_permissions'=>[
        'ADMIN'=>['sim_hosting.view','sim_hosting.providers.manage','sim_hosting.settings.manage','sim_hosting.transactions.view'],
        'STAFF'=>['sim_hosting.view','sim_hosting.transactions.view'],
        'SUPPORT'=>['sim_hosting.view','sim_hosting.transactions.view'],
        'USER'=>[],
    ],
    'navigation'=>[],
    'admin_navigation'=>[
        ['id'=>'admin-sim-hosting','label'=>'SIM Hosting Provider','url'=>'/admin/sim-hosting','icon'=>'server','permission'=>'sim_hosting.view','section'=>'addons','order'=>70]
    ],
    'settings'=>[
        ['key'=>'enabled','type'=>'boolean','default'=>true],
        ['key'=>'default_currency','type'=>'string','default'=>'NGN'],
        ['key'=>'provider_service_key','type'=>'string','default'=>'sim-hosting'],
    ],
    'migrations'=>[
        '2026_10_07_000700_create_sim_hosting_products.php',
        '2026_10_07_000701_create_sim_hosting_numbers.php',
        '2026_10_07_000702_create_sim_hosting_rentals.php',
        '2026_10_07_000703_create_sim_hosting_movements.php',
        '2026_10_07_000704_add_provider_inventory_fields.php',
        '2026_10_07_000705_add_provider_fields_to_rentals.php'
    ],
    'web_route_files'=>[],
    'api_route_files'=>[],
    'routes'=>[],
    'api_routes'=>[],
    'provider_integrations'=>['Core ProviderManager'],
    'provider_capabilities'=>[
        'airtime_purchase',
        'data_purchase',
        'data_catalogue',
        'sms_send',
        'sms_balance',
        'transaction_status',
        'transaction_requery',
        'provider_balance',
        'webhook'
    ],
    'capabilities'=>[
        'data','airtime','sms','data_catalogue','transaction_status','transaction_requery','provider_balance','webhook'
    ],
    'scheduled_tasks'=>['provider health and catalogue reconciliation'],
    'events'=>['sim_hosting.provider.requested','sim_hosting.provider.completed','sim_hosting.provider.failed'],
];