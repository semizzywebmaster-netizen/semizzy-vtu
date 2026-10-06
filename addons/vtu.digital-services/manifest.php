<?php

return [
    'identifier'=>'vtu.digital-services','name'=>'VTU & Digital Services','version'=>'1.0.0',
    'compatibility'=>'>=2.0.0','dependencies'=>[],
    'permissions'=>['vtu.view','vtu.services.manage','vtu.products.manage','vtu.providers.manage','vtu.mappings.manage','vtu.transactions.view','vtu.transactions.manage','vtu.bulk.manage','vtu.requery','vtu.refunds.manage','vtu.settings.manage'],
    'navigation'=>[['id'=>'vtu','label'=>'VTU','url'=>'/admin/vtu','icon'=>'server','permission'=>'vtu.view','section'=>'addons','order'=>10]],
    'settings'=>[],
    'migrations'=>['2026_10_05_000026_create_vtu_addon_tables.php','2026_10_05_000027_add_vtu_bulk_idempotency.php'],
    'routes'=>['/vtu'],'api_routes'=>['/api/v1/vtu'],'services'=>['provider-driven digital services'],
    'provider_integrations'=>['Core ProviderManager'],'scheduled_tasks'=>['pending transaction reconciliation'],'events'=>[],
];
