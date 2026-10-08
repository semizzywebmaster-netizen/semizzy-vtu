<?php
return [
 'identifier'=>'marketplace.commerce','name'=>'Marketplace','version'=>'1.1.0',
 'description'=>'Unified multi-vendor commerce marketplace for physical new/used products, digital products and services with inventory, delivery, orders, seller earnings, reviews, refunds and addon-safe payment integration.',
 'compatibility'=>'>=2.0.0','dependencies'=>[],
 'permissions'=>['marketplace.view','marketplace.buy','marketplace.sell','marketplace.manage','marketplace.orders.view','marketplace.orders.manage','marketplace.products.manage','marketplace.settings.manage'],
 'role_permissions'=>[
  'ADMIN'=>['marketplace.view','marketplace.buy','marketplace.sell','marketplace.manage','marketplace.orders.view','marketplace.orders.manage','marketplace.products.manage','marketplace.settings.manage'],
  'STAFF'=>['marketplace.view','marketplace.orders.view','marketplace.orders.manage','marketplace.products.manage'],
  'SUPPORT'=>['marketplace.view','marketplace.orders.view'],
  'USER'=>['marketplace.view','marketplace.buy','marketplace.sell','marketplace.orders.view'],
 ],
 'navigation'=>[['id'=>'marketplace','label'=>'Marketplace','url'=>'/marketplace','icon'=>'shopping-bag','permission'=>'marketplace.view','section'=>'services','order'=>140]],
 'admin_navigation'=>[['id'=>'admin-marketplace','label'=>'Marketplace','url'=>'/admin/marketplace','icon'=>'shopping-bag','permission'=>'marketplace.manage','section'=>'addons','order'=>140]],
 'settings'=>[
  ['key'=>'enabled','type'=>'boolean','default'=>true],
  ['key'=>'default_currency','type'=>'string','default'=>'NGN'],
  ['key'=>'platform_fee_bps','type'=>'integer','default'=>0],
  ['key'=>'max_order_minor','type'=>'integer','default'=>1000000000],
 ],
 'migrations'=>['2026_10_07_001100_create_marketplace_tables.php','2026_10_07_001101_harden_marketplace_orders.php','2026_10_07_001102_marketplace_earnings.php','2026_10_08_006000_extend_marketplace_product_types.php'],
 'web_route_files'=>['addons/marketplace.commerce/routes/web.php','addons/marketplace.commerce/routes/admin.php'],
 'api_route_files'=>['addons/marketplace.commerce/routes/api.php'],
 'routes'=>['/marketplace'],'api_routes'=>['/api/v1/marketplace/orders'],
 'provider_integrations'=>[],'provider_capabilities'=>[],'scheduled_tasks'=>[],
 'events'=>['marketplace.order.created','marketplace.order.paid','marketplace.order.cancelled','marketplace.order.refunded'],
];