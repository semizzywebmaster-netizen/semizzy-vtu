<?php
return [
 'identifier'=>'p2p.transfers','name'=>'P2P Transfers','version'=>'1.0.0',
 'description'=>'Secure wallet-to-wallet peer-to-peer transfers with idempotency, transaction PIN protection, immutable wallet movements and audit-safe status handling.',
 'compatibility'=>'>=2.0.0','dependencies'=>[],
 'permissions'=>['p2p.view','p2p.send','p2p.manage','p2p.transactions.view','p2p.settings.manage'],
 'role_permissions'=>[
  'ADMIN'=>['p2p.view','p2p.send','p2p.manage','p2p.transactions.view','p2p.settings.manage'],
  'STAFF'=>['p2p.view','p2p.transactions.view'],
  'SUPPORT'=>['p2p.view','p2p.transactions.view'],
  'USER'=>['p2p.view','p2p.send','p2p.transactions.view'],
 ],
 'navigation'=>[['id'=>'p2p-transfers','label'=>'P2P Transfers','url'=>'/p2p/transfers','icon'=>'send','permission'=>'p2p.view','section'=>'services','order'=>130]],
 'admin_navigation'=>[['id'=>'admin-p2p-transfers','label'=>'P2P Transfers','url'=>'/admin/p2p/transfers','icon'=>'send','permission'=>'p2p.view','section'=>'addons','order'=>130]],
 'settings'=>[
  ['key'=>'enabled','type'=>'boolean','default'=>true],
  ['key'=>'default_currency','type'=>'string','default'=>'NGN'],
  ['key'=>'fee_minor','type'=>'integer','default'=>0],
  ['key'=>'max_transfer_minor','type'=>'integer','default'=>1000000000],
 ],
 'migrations'=>['2026_10_08_001300_create_p2p_transfers.php'],
 'web_route_files'=>['addons/p2p.transfers/routes/web.php','addons/p2p.transfers/routes/admin.php'],
 'api_route_files'=>['addons/p2p.transfers/routes/api.php'],
 'routes'=>['/p2p/transfers'],'api_routes'=>['/api/v1/p2p/transfers'],
 'provider_integrations'=>[],
 'provider_capabilities'=>[],
 'scheduled_tasks'=>[],
 'events'=>['p2p.transfer.created','p2p.transfer.completed','p2p.transfer.failed'],
];