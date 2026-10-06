<?php

return [
    'identifier'=>'vtu.digital-services','name'=>'VTU & Digital Services','version'=>'1.0.0',
    'compatibility'=>'>=2.0.0','dependencies'=>[],
    'permissions'=>['vtu.view','vtu.services.manage','vtu.products.manage','vtu.providers.manage','vtu.mappings.manage','vtu.transactions.view','vtu.transactions.manage','vtu.bulk.manage','vtu.requery','vtu.refunds.manage','vtu.settings.manage'],
    'navigation'=>[
        ['id'=>'vtu-control','label'=>'VTU Control Center','url'=>'/admin/vtu','icon'=>'server','permission'=>'vtu.view','section'=>'addons','order'=>10],
        ['id'=>'vtu-services','label'=>'VTU Services','url'=>'/admin/vtu/services','icon'=>'catalogue','permission'=>'vtu.services.manage','section'=>'addons','order'=>11],
        ['id'=>'vtu-products','label'=>'VTU Products','url'=>'/admin/vtu/products','icon'=>'catalogue','permission'=>'vtu.products.manage','section'=>'addons','order'=>12],
        ['id'=>'vtu-mappings','label'=>'VTU Mappings','url'=>'/admin/vtu/mappings','icon'=>'catalogue','permission'=>'vtu.mappings.manage','section'=>'addons','order'=>13],
        ['id'=>'vtu-transactions','label'=>'VTU Transactions','url'=>'/admin/vtu/transactions','icon'=>'audit','permission'=>'vtu.transactions.view','section'=>'addons','order'=>14],
        ['id'=>'vtu-bulk','label'=>'VTU Bulk Operations','url'=>'/admin/vtu/bulk','icon'=>'settings','permission'=>'vtu.bulk.manage','section'=>'addons','order'=>15],
    ],
    'settings'=>[],
    'migrations'=>['2026_10_05_000026_create_vtu_addon_tables.php','2026_10_05_000027_add_vtu_bulk_idempotency.php'],
    'installer'=>'App\\Services\\Vtu\\VtuAddonInstaller',
    'routes'=>['/vtu'],'api_routes'=>['/api/v1/vtu'],'services'=>['provider-driven digital services'],
    'provider_integrations'=>['Core ProviderManager'],'scheduled_tasks'=>['pending transaction reconciliation'],'events'=>[],
];
