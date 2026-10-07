<?php
return [
 'identifier'=>'mailer.smtp',
 'name'=>'Mailer SMTP',
 'version'=>'0.1.0',
 'description'=>'Admin-managed multi-SMTP mailer with more than 10 profiles, priority failover, health tracking and safe credential handling.',
 'compatibility'=>'>=2.0.0','dependencies'=>[],
 'permissions'=>['mailer.view','mailer.manage','mailer.send','mailer.settings.manage'],
 'role_permissions'=>[
  'ADMIN'=>['mailer.view','mailer.manage','mailer.send','mailer.settings.manage'],
  'STAFF'=>['mailer.view','mailer.send'],
  'SUPPORT'=>['mailer.view'],
  'USER'=>[],
 ],
 'admin_navigation'=>[['id'=>'admin-mailer-smtp','label'=>'Mailer SMTP','url'=>'/admin/mailer-smtp','icon'=>'mail','permission'=>'mailer.manage','section'=>'addons','order'=>176]],
 'settings'=>[
  ['key'=>'enabled','type'=>'boolean','default'=>true],
  ['key'=>'strategy','type'=>'string','default'=>'failover'],
  ['key'=>'max_profiles','type'=>'integer','default'=>50],
  ['key'=>'health_check_enabled','type'=>'boolean','default'=>true],
  ['key'=>'retry_failed_profile','type'=>'boolean','default'=>true],
 ],
 'migrations'=>['2026_10_07_020001_create_mailer_smtp_profiles.php'],
 'web_route_files'=>['addons/mailer-smtp/routes/admin.php'],
 'api_route_files'=>['addons/mailer-smtp/routes/api.php'],
 'routes'=>['/admin/mailer-smtp'],'api_routes'=>['/api/v1/mailer-smtp'],
 'provider_integrations'=>[],'provider_capabilities'=>[],
 'capabilities'=>['multi_smtp_profiles','priority_failover','health_checks','credential_encryption','smtp_test','delivery_logs','safe_retry'],
];