<?php
return [
 'identifier'=>'gift.cards','name'=>'Gift Cards Marketplace','version'=>'1.0.0',
 'description'=>'Modular gift-card marketplace with provider fulfillment, inventory, wallet payment, secure delivery, requery and refund workflows.',
 'compatibility'=>'>=2.0.0','dependencies'=>[],
 'permissions'=>['giftcards.view','giftcards.buy','giftcards.manage','giftcards.products.manage','giftcards.providers.manage','giftcards.refunds.manage','giftcards.settings.manage','giftcards.audit'],
 'role_permissions'=>[
  'ADMIN'=>['giftcards.view','giftcards.buy','giftcards.manage','giftcards.products.manage','giftcards.providers.manage','giftcards.refunds.manage','giftcards.settings.manage','giftcards.audit'],
  'STAFF'=>['giftcards.view','giftcards.manage','giftcards.products.manage','giftcards.audit'],
  'SUPPORT'=>['giftcards.view','giftcards.manage','giftcards.refunds.manage','giftcards.audit'],
  'USER'=>['giftcards.view','giftcards.buy']],
 'navigation'=>[['id'=>'gift-cards','label'=>'Gift Cards','url'=>'/gift-cards','icon'=>'gift','permission'=>'giftcards.view','section'=>'services','order'=>190]],
 'admin_navigation'=>[['id'=>'admin-gift-cards','label'=>'Gift Cards','url'=>'/admin/gift-cards','icon'=>'gift','permission'=>'giftcards.view','section'=>'addons','order'=>200]],
 'settings'=>[['key'=>'enabled','type'=>'boolean','default'=>false],['key'=>'default_currency','type'=>'string','default'=>'NGN'],['key'=>'require_transaction_pin','type'=>'boolean','default'=>true],['key'=>'manual_fulfillment_enabled','type'=>'boolean','default'=>true]],
 'migrations'=>['2026_10_07_040000_create_gift_cards_tables.php'],
 'web_route_files'=>['addons/gift-cards/routes/web.php','addons/gift-cards/routes/admin.php'],'api_route_files'=>['addons/gift-cards/routes/api.php'],
 'routes'=>['/gift-cards','/admin/gift-cards'],'api_routes'=>['/api/v1/gift-cards'],
 'provider_integrations'=>['Core Provider Engine','Core Wallet/Ledger','Core Notifications','Core Audit'],
 'provider_capabilities'=>['giftcard_catalog','giftcard_balance','giftcard_purchase','giftcard_requery','giftcard_cancel','giftcard_refund'],
 'capabilities'=>['catalog','fixed_denominations','variable_denominations','provider_failover','inventory','encrypted_codes','wallet_payment','transaction_pin','idempotency','requery','refunds','manual_fulfillment','receipts','audit_history'],
];