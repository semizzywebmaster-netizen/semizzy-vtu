<?php
return [
 'identifier'=>'p2p.transfers','name'=>'P2P Transfers & Trading','version'=>'1.1.0',
 'description'=>'Secure wallet-to-wallet transfers plus peer-to-peer trading listings and offers, with optional integration to the central Escrow Protection addon.',
 'compatibility'=>'>=2.0.0','dependencies'=>[],
 'permissions'=>[
  'p2p.view','p2p.send','p2p.manage','p2p.transactions.view','p2p.settings.manage',
  'p2p.listings.view','p2p.listings.manage','p2p.offers.manage'
 ],
 'role_permissions'=>[
  'ADMIN'=>['p2p.view','p2p.send','p2p.manage','p2p.transactions.view','p2p.settings.manage','p2p.listings.view','p2p.listings.manage','p2p.offers.manage'],
  'STAFF'=>['p2p.view','p2p.transactions.view','p2p.listings.view','p2p.listings.manage','p2p.offers.manage'],
  'SUPPORT'=>['p2p.view','p2p.transactions.view','p2p.listings.view'],
  'USER'=>['p2p.view','p2p.send','p2p.transactions.view','p2p.listings.view','p2p.listings.manage','p2p.offers.manage'],
 ],
 'navigation'=>[
  ['id'=>'p2p-transfers','label'=>'P2P Transfers & Trading','url'=>'/p2p/transfers','icon'=>'send','permission'=>'p2p.view','section'=>'services','order'=>130]
 ],
 'admin_navigation'=>[
  ['id'=>'admin-p2p-transfers','label'=>'P2P Transfers & Trading','url'=>'/admin/p2p/transfers','icon'=>'send','permission'=>'p2p.view','section'=>'addons','order'=>130]
 ],
 'settings'=>[
  ['key'=>'enabled','type'=>'boolean','default'=>true],
  ['key'=>'default_currency','type'=>'string','default'=>'NGN'],
  ['key'=>'fee_minor','type'=>'integer','default'=>0],
  ['key'=>'min_transfer_minor','type'=>'integer','default'=>100],
  ['key'=>'max_transfer_minor','type'=>'integer','default'=>1000000000],
  ['key'=>'high_amount_transfer_threshold_minor','type'=>'integer','default'=>100000000],
  ['key'=>'high_amount_transfer_require_otp','type'=>'boolean','default'=>true],
  ['key'=>'high_amount_transfer_require_pin','type'=>'boolean','default'=>true],
  ['key'=>'max_trade_minor','type'=>'integer','default'=>1000000000],
  ['key'=>'offer_expiry_hours','type'=>'integer','default'=>24],
 ],
 'migrations'=>['2026_10_08_001300_create_p2p_transfers.php','2026_10_07_000801_create_p2p_trading.php'],
 'web_route_files'=>['addons/p2p.transfers/routes/web.php','addons/p2p.transfers/routes/admin.php'],
 'api_route_files'=>['addons/p2p.transfers/routes/api.php'],
 'routes'=>['/p2p/transfers'],'api_routes'=>['/api/v1/p2p/transfers','/api/v1/p2p/trading'],
 'provider_integrations'=>[],'provider_capabilities'=>[],
 'capabilities'=>[
  'wallet_transfer','recipient_validation','idempotent_transfer','transfer_history',
  'trade_listings','trade_offers','trade_negotiation','trade_expiry','trade_escrow_link',
  'trade_dispute_link','trade_reputation_hooks'
 ],
 'scheduled_tasks'=>['expire stale P2P trade offers'],
 'events'=>['p2p.transfer.created','p2p.transfer.completed','p2p.transfer.failed','p2p.listing.created','p2p.offer.created','p2p.offer.accepted','p2p.offer.rejected','p2p.offer.expired'],
];