<?php
return [
 'identifier'=>'social.accounts-verification',
 'name'=>'Social Media Accounts & Foreign Verification Numbers',
 'version'=>'0.1.0',
 'autoload_namespace'=>'Semizzy\\Addons\\Social',
 'description'=>'Admin-supplied social media account services and foreign verification numbers with API and manual fulfillment, including purchased-number SMS inboxes.',
 'compatibility'=>'>=2.0.0',
 'dependencies'=>[],
 'permissions'=>[
  'social.view','social.orders.manage','social.accounts.manage','social.numbers.manage',
  'social.sms.view','social.sms.manage','social.settings.manage'
 ],
 'role_permissions'=>[
  'ADMIN'=>['social.view','social.orders.manage','social.accounts.manage','social.numbers.manage','social.sms.view','social.sms.manage','social.settings.manage'],
  'STAFF'=>['social.view','social.orders.manage','social.accounts.manage','social.numbers.manage','social.sms.view'],
  'SUPPORT'=>['social.view','social.orders.manage','social.sms.view'],
  'USER'=>['social.view','social.orders.manage','social.sms.view'],
 ],
 'navigation'=>[['id'=>'social-services','label'=>'Social Services','url'=>'/social-services','icon'=>'share','permission'=>'social.view','section'=>'services','order'=>150]],
 'admin_navigation'=>[['id'=>'admin-social-services','label'=>'Social Services','url'=>'/admin/social-services','icon'=>'share','permission'=>'social.view','section'=>'addons','order'=>150]],
 'settings'=>[
  ['key'=>'enabled','type'=>'boolean','default'=>true],
  ['key'=>'account_fulfillment_mode','type'=>'string','default'=>'api_or_manual'],
  ['key'=>'number_fulfillment_mode','type'=>'string','default'=>'api_or_manual'],
  ['key'=>'sms_polling_enabled','type'=>'boolean','default'=>true],
 ],
 'migrations'=>['2026_10_07_003000_create_social_services_tables.php','2026_10_09_120000_add_social_order_payment_tracking.php'],
 'web_route_files'=>['addons/social.accounts-verification/routes/web.php','addons/social.accounts-verification/routes/admin.php'],
 'api_route_files'=>['addons/social.accounts-verification/routes/api.php'],
 'routes'=>['/social-services','/admin/social-services'],
 'api_routes'=>['/api/v1/social-services'],
 'provider_integrations'=>['Core ProviderManager'],
 'provider_capabilities'=>['social_account_purchase','foreign_number_purchase','foreign_number_status','foreign_number_sms'],
 'capabilities'=>['admin_account_inventory','admin_number_inventory','api_fulfillment','manual_fulfillment','number_sms_inbox','orders','requery','provider_failover'],
];