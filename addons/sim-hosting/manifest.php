<?php

return [
    'identifier'=>'sim-hosting','name'=>'SIM Hosting & Virtual Numbers','version'=>'1.0.0',
    'description'=>'Provider-driven virtual number and SIM hosting rentals with inventory, allocation, renewal and expiry management.',
    'compatibility'=>'>=2.0.0','dependencies'=>[],
    'permissions'=>['sim_hosting.view','sim_hosting.rent','sim_hosting.manage','sim_hosting.providers.manage','sim_hosting.settings.manage','sim_hosting.transactions.view'],
    'role_permissions'=>[
        'ADMIN'=>['sim_hosting.view','sim_hosting.rent','sim_hosting.manage','sim_hosting.providers.manage','sim_hosting.settings.manage','sim_hosting.transactions.view'],
        'STAFF'=>['sim_hosting.view','sim_hosting.manage','sim_hosting.providers.manage','sim_hosting.transactions.view'],
        'SUPPORT'=>['sim_hosting.view','sim_hosting.transactions.view'],'USER'=>['sim_hosting.view','sim_hosting.rent'],
    ],
    'navigation'=>[['id'=>'sim-hosting','label'=>'SIM Hosting','url'=>'/sim-hosting','icon'=>'phone','permission'=>'sim_hosting.view','section'=>'services','order'=>70]],
    'admin_navigation'=>[['id'=>'admin-sim-hosting','label'=>'SIM Hosting','url'=>'/admin/sim-hosting','icon'=>'phone','permission'=>'sim_hosting.view','section'=>'addons','order'=>70]],
    'settings'=>[['key'=>'enabled','type'=>'boolean','default'=>true],['key'=>'default_currency','type'=>'string','default'=>'NGN'],['key'=>'default_rental_days','type'=>'integer','default'=>30],['key'=>'max_rental_days','type'=>'integer','default'=>365],['key'=>'auto_expire_enabled','type'=>'boolean','default'=>true]],
    'migrations'=>['2026_10_07_000700_create_sim_hosting_products.php','2026_10_07_000701_create_sim_hosting_numbers.php','2026_10_07_000702_create_sim_hosting_rentals.php','2026_10_07_000703_create_sim_hosting_movements.php','2026_10_07_000704_add_provider_inventory_fields.php'],
    'web_route_files'=>['addons/sim-hosting/routes/web.php','addons/sim-hosting/routes/admin.php'],
    'api_route_files'=>['addons/sim-hosting/routes/api.php'],'routes'=>['/sim-hosting'],'api_routes'=>['/api/v1/sim-hosting'],
    'provider_integrations'=>['Core ProviderManager'],'scheduled_tasks'=>['SIM rental expiry and provider inventory reconciliation'],
    'events'=>['sim_hosting.rental.created','sim_hosting.rental.renewed','sim_hosting.rental.expired','sim_hosting.number.status_changed'],
];