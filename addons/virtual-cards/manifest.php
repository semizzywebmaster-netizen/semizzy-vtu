<?php
return [
 'identifier'=>'virtual.cards',
 'autoload_namespace'=>'Addons\\VirtualCards',
 'name'=>'Virtual Cards',
 'version'=>'1.0.0',
 'description'=>'Provider-backed virtual card lifecycle, controls, limits and transaction records.',
 'core_compatibility'=>'>=2.0.0','dependencies'=>[],
 'permissions'=>['virtual-cards.view','virtual-cards.manage','virtual-cards.freeze','virtual-cards.audit'],
 'role_permissions'=>[
  'ADMIN'=>['virtual-cards.view','virtual-cards.manage','virtual-cards.freeze','virtual-cards.audit'],
  'STAFF'=>['virtual-cards.view','virtual-cards.manage','virtual-cards.freeze'],
  'SUPPORT'=>['virtual-cards.view'],'USER'=>['virtual-cards.view','virtual-cards.manage'],
 ],
 'navigation'=>[['id'=>'virtual-cards','label'=>'Virtual Cards','url'=>'/virtual-cards','icon'=>'credit-card','permission'=>'virtual-cards.view','section'=>'services','order'=>94]],
 'settings'=>[
  ['key'=>'enabled','type'=>'boolean','default'=>true],
  ['key'=>'issuance_enabled','type'=>'boolean','default'=>false],
  ['key'=>'default_currency','type'=>'string','default'=>'NGN'],
 ],
 'migrations'=>['2026_10_08_090000_create_virtual_card_tables.php'],
 'web_route_files'=>['addons/virtual-cards/routes/web.php'],
 'api_route_files'=>['addons/virtual-cards/routes/api.php'],
 'provider_integrations'=>['Core Provider Engine','Core wallet','Core transactions','Core audit'],
 'capabilities'=>['card_requests','provider_issuance','freeze_unfreeze','spending_limits','transaction_history','audit_metadata'],
];