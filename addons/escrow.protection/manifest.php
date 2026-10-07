<?php
return [
 'identifier'=>'escrow.protection','name'=>'Escrow','version'=>'1.0.0',
 'description'=>'Protected buyer-seller transactions with wallet holds, controlled release, cancellation, disputes and refunds.',
 'compatibility'=>'>=2.0.0','dependencies'=>['p2p.transfers'],
 'permissions'=>['escrow.view','escrow.create','escrow.manage','escrow.release','escrow.dispute','escrow.refund','escrow.settings.manage'],
 'role_permissions'=>[
  'ADMIN'=>['escrow.view','escrow.create','escrow.manage','escrow.release','escrow.dispute','escrow.refund','escrow.settings.manage'],
  'STAFF'=>['escrow.view','escrow.manage','escrow.dispute'],
  'SUPPORT'=>['escrow.view','escrow.dispute'],
  'USER'=>['escrow.view','escrow.create','escrow.release','escrow.dispute'],
 ],
 'navigation'=>[['id'=>'escrow','label'=>'Escrow','url'=>'/escrow','icon'=>'shield-check','permission'=>'escrow.view','section'=>'services','order'=>140]],
 'admin_navigation'=>[['id'=>'admin-escrow','label'=>'Escrow','url'=>'/admin/escrow','icon'=>'shield-check','permission'=>'escrow.manage','section'=>'addons','order'=>140]],
 'settings'=>[
  ['key'=>'enabled','type'=>'boolean','default'=>true],
  ['key'=>'default_currency','type'=>'string','default'=>'NGN'],
  ['key'=>'fee_minor','type'=>'integer','default'=>0],
  ['key'=>'max_amount_minor','type'=>'integer','default'=>1000000000],
  ['key'=>'default_expiry_hours','type'=>'integer','default'=>72],
 ],
 'migrations'=>['2026_10_08_001400_create_escrow_tables.php'],
 'web_route_files'=>['addons/escrow.protection/routes/web.php','addons/escrow.protection/routes/admin.php'],
 'api_route_files'=>['addons/escrow.protection/routes/api.php'],
 'routes'=>['/escrow','/admin/escrow'],'api_routes'=>['/api/v1/escrow'],
 'provider_integrations'=>[],'provider_capabilities'=>[],'scheduled_tasks'=>['escrow.expiry_reconciliation'],
 'events'=>['escrow.created','escrow.funded','escrow.released','escrow.cancelled','escrow.disputed','escrow.refunded'],
];