<?php
return [
 'identifier'=>'whatsapp.bot','name'=>'WhatsApp Bot','version'=>'1.0.0',
 'autoload_namespace'=>'Addons\\WhatsAppBot',
 'description'=>'Verified-account WhatsApp bot for service discovery, transaction commands, receipts and transaction updates. Only the WhatsApp number verified on a SEMIZZY ONE account may transact.',
 'compatibility'=>'>=2.0.0','dependencies'=>['communication.whatsapp','vtu.digital-services'],
 'permissions'=>['whatsapp_bot.view','whatsapp_bot.manage','whatsapp_bot.verify','whatsapp_bot.transact','whatsapp_bot.audit'],
 'role_permissions'=>[
  'ADMIN'=>['whatsapp_bot.view','whatsapp_bot.manage','whatsapp_bot.verify','whatsapp_bot.transact','whatsapp_bot.audit'],
  'STAFF'=>['whatsapp_bot.view','whatsapp_bot.verify'],
  'SUPPORT'=>['whatsapp_bot.view','whatsapp_bot.verify'],
  'USER'=>['whatsapp_bot.view','whatsapp_bot.transact']
 ],
 'navigation'=>[['id'=>'whatsapp-bot','label'=>'WhatsApp Bot','url'=>'/whatsapp-bot','icon'=>'message-circle','permission'=>'whatsapp_bot.view','section'=>'services','order'=>95]],
 'admin_navigation'=>[['id'=>'admin-whatsapp-bot','label'=>'WhatsApp Bot','url'=>'/admin/whatsapp-bot','icon'=>'message-circle','permission'=>'whatsapp_bot.view','section'=>'addons','order'=>95]],
 'settings'=>[
  ['key'=>'enabled','type'=>'boolean','default'=>true],
  ['key'=>'transactions_enabled','type'=>'boolean','default'=>true],
  ['key'=>'verification_required','type'=>'boolean','default'=>true],
  ['key'=>'otp_expiry_minutes','type'=>'integer','default'=>10],
  ['key'=>'otp_resend_seconds','type'=>'integer','default'=>60]
 ],
 'routes'=>['/whatsapp-bot','/whatsapp-bot/verify'],
 'api_routes'=>['/api/v1/whatsapp-bot/webhook'],
 'web_route_files'=>['addons/whatsapp-bot/routes/web.php'],
 'api_route_files'=>['addons/whatsapp-bot/routes/api.php'],
 'provider_integrations'=>['Communication & WhatsApp','Core ProviderManager','VTU & Digital Services'],
 'provider_capabilities'=>['whatsapp_receive','whatsapp_send'],
 'capabilities'=>[
  'registered_number_only','whatsapp_number_verification','transaction_gate','service_purchase_commands',
  'transaction_status','receipts','transaction_notifications','idempotent_commands','audit_trail'
 ],
 'events'=>['whatsapp.bot.message.received','whatsapp.bot.verification.completed','whatsapp.bot.transaction.requested','whatsapp.bot.transaction.completed','whatsapp.bot.transaction.failed']
];
