<?php
return [
 'identifier'=>'communication.whatsapp','name'=>'Communication & WhatsApp','version'=>'1.0.0',
 'autoload_namespace'=>'Addons\\CommunicationWhatsapp',
 'description'=>'Unified WhatsApp, SMS, email and web-push communication center with conversations, campaigns, templates, consent and provider failover.',
 'compatibility'=>'>=2.0.0','dependencies'=>['bulk-sms.communication'],
 'permissions'=>[
  'communication.view','communication.send','communication.manage','communication.providers.manage',
  'communication.templates.manage','communication.campaigns.manage','communication.conversations.manage',
  'communication.consent.manage','communication.settings.manage','communication.audit'
 ],
 'role_permissions'=>[
  'ADMIN'=>['communication.view','communication.send','communication.manage','communication.providers.manage','communication.templates.manage','communication.campaigns.manage','communication.conversations.manage','communication.consent.manage','communication.settings.manage','communication.audit'],
  'STAFF'=>['communication.view','communication.send','communication.conversations.manage','communication.templates.manage','communication.campaigns.manage'],
  'SUPPORT'=>['communication.view','communication.conversations.manage','communication.send'],
  'USER'=>['communication.view','communication.send']
 ],
 'navigation'=>[['id'=>'communication','label'=>'Communication','url'=>'/communication','icon'=>'messages-square','permission'=>'communication.view','section'=>'services','order'=>90]],
 'admin_navigation'=>[['id'=>'admin-communication','label'=>'Communication Center','url'=>'/admin/communication','icon'=>'messages-square','permission'=>'communication.view','section'=>'addons','order'=>90]],
 'settings'=>[
  ['key'=>'enabled','type'=>'boolean','default'=>true],
  ['key'=>'whatsapp_enabled','type'=>'boolean','default'=>true],
  ['key'=>'sms_enabled','type'=>'boolean','default'=>true],
  ['key'=>'email_enabled','type'=>'boolean','default'=>true],
  ['key'=>'web_push_enabled','type'=>'boolean','default'=>true],
  ['key'=>'provider_failover_enabled','type'=>'boolean','default'=>true]
 ],
 'migrations'=>[
  '2026_10_07_050000_create_communication_providers.php',
  '2026_10_07_050001_create_communication_templates.php',
  '2026_10_07_050002_create_communication_campaigns.php',
  '2026_10_07_050003_create_communication_conversations.php',
  '2026_10_07_050004_create_communication_messages.php',
  '2026_10_07_050005_create_communication_consents.php',
  '2026_10_07_050006_create_communication_delivery_attempts.php'
 ],
 'provider_integrations'=>['Core ProviderManager','Mailer SMTP','Bulk SMS & Messaging'],
 'provider_capabilities'=>['whatsapp_send','whatsapp_receive','whatsapp_status','sms_send','email_send','push_send'],
 'events'=>['communication.message.queued','communication.message.sent','communication.message.delivered','communication.message.failed','communication.conversation.received'],
];