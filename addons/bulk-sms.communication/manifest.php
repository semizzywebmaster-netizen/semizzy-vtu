<?php
return [
 'identifier'=>'bulk-sms.communication','name'=>'Bulk SMS & Messaging','version'=>'1.0.0',
 'description'=>'Provider-driven SMS messaging with sender IDs, campaigns, contacts, scheduling, delivery reconciliation and safe wallet billing.',
 'compatibility'=>'>=2.0.0','dependencies'=>[],
 'permissions'=>['bulk_sms.view','bulk_sms.send','bulk_sms.manage','bulk_sms.providers.manage','bulk_sms.contacts.manage','bulk_sms.settings.manage','bulk_sms.transactions.view'],
 'role_permissions'=>[
  'ADMIN'=>['bulk_sms.view','bulk_sms.send','bulk_sms.manage','bulk_sms.providers.manage','bulk_sms.contacts.manage','bulk_sms.settings.manage','bulk_sms.transactions.view'],
  'STAFF'=>['bulk_sms.view','bulk_sms.send','bulk_sms.manage','bulk_sms.contacts.manage','bulk_sms.transactions.view'],
  'SUPPORT'=>['bulk_sms.view','bulk_sms.transactions.view'],'USER'=>['bulk_sms.view','bulk_sms.send','bulk_sms.contacts.manage']],
 'navigation'=>[['id'=>'bulk-sms','label'=>'Bulk SMS','url'=>'/bulk-sms','icon'=>'message-square','permission'=>'bulk_sms.view','section'=>'services','order'=>80]],
 'admin_navigation'=>[['id'=>'admin-bulk-sms','label'=>'Bulk SMS','url'=>'/admin/bulk-sms','icon'=>'message-square','permission'=>'bulk_sms.view','section'=>'addons','order'=>80]],
 'settings'=>[['key'=>'enabled','type'=>'boolean','default'=>true],['key'=>'default_currency','type'=>'string','default'=>'NGN'],['key'=>'max_recipients_per_campaign','type'=>'integer','default'=>10000],['key'=>'delivery_reconciliation_enabled','type'=>'boolean','default'=>true]],
 'migrations'=>['2026_10_08_000800_create_bulk_sms_products.php','2026_10_08_000801_create_bulk_sms_sender_ids.php','2026_10_08_000802_create_bulk_sms_contacts.php','2026_10_08_000803_create_bulk_sms_campaigns.php','2026_10_08_000804_create_bulk_sms_messages.php'],
 'web_route_files'=>['addons/bulk-sms.communication/routes/web.php','addons/bulk-sms.communication/routes/admin.php'],
 'api_route_files'=>['addons/bulk-sms.communication/routes/api.php'],
 'routes'=>['/bulk-sms'],'api_routes'=>['/api/v1/bulk-sms'],
 'provider_integrations'=>['Core ProviderManager'],'provider_capabilities'=>['sms_send','sms_status'],
 'scheduled_tasks'=>['Bulk SMS scheduled dispatch and delivery reconciliation','Schedule editing cutoff enforcement'],
 'events'=>['bulk_sms.campaign.created','bulk_sms.message.sent','bulk_sms.message.delivered','bulk_sms.message.failed'],
];