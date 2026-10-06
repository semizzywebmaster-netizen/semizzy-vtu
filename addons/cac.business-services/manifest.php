<?php

return [
    'identifier'=>'cac.business-services','name'=>'CAC Business Services','version'=>'1.0.0',
    'compatibility'=>'>=2.0.0','dependencies'=>[],
    'permissions'=>['cac.view','cac.orders.manage','cac.products.manage','cac.providers.manage','cac.documents.manage','cac.settings.manage','cac.transactions.view'],
    'navigation'=>[['id'=>'cac','label'=>'CAC Services','url'=>'/admin/cac','icon'=>'briefcase','permission'=>'cac.view','section'=>'addons','order'=>20]],
    'settings'=>[['key'=>'default_review_state','type'=>'string','default'=>'pending_review'],['key'=>'document_retention_days','type'=>'integer','default'=>365]],
    'migrations'=>['2026_10_06_000100_create_cac_addon_tables.php','2026_10_06_000101_create_cac_catalogue_tables.php','2026_10_06_000102_add_cac_service_product_to_orders.php','2026_10_06_000103_create_cac_order_status_histories.php','2026_10_06_000105_create_cac_webhook_events.php','2026_10_06_000106_harden_cac_document_review.php'],
    'routes'=>['/cac'],'api_routes'=>['/api/v1/cac'],
    'services'=>['business-name registration','company registration','CAC search and verification','document/order workflow'],
    'provider_integrations'=>['Core ProviderManager'],'scheduled_tasks'=>['pending CAC order reconciliation'],
    'events'=>['cac.order.created','cac.order.status.changed','cac.order.completed'],
];
