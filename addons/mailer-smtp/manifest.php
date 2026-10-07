<?php
return [
 'identifier'=>'mailer.smtp','name'=>'Mailer SMTP / SMTP Mailer','version'=>'1.0.0',
 'description'=>'Central SMTP profile pool with unlimited profiles, priority failover, health state and secure credential handling.',
 'compatibility'=>'>=2.0.0','dependencies'=>[],
 'permissions'=>['mailer.view','mailer.manage','mailer.settings.manage','mailer.audit'],
 'role_permissions'=>['ADMIN'=>['mailer.view','mailer.manage','mailer.settings.manage','mailer.audit'],'STAFF'=>['mailer.view','mailer.manage','mailer.audit'],'SUPPORT'=>['mailer.view','mailer.audit'],'USER'=>[]],
 'admin_navigation'=>[['id'=>'admin-mailer-smtp','label'=>'Mailer SMTP','url'=>'/admin/mailer-smtp','icon'=>'mail','permission'=>'mailer.view','section'=>'settings','order'=>190]],
 'settings'=>[['key'=>'enabled','type'=>'boolean','default'=>false],['key'=>'strategy','type'=>'string','default'=>'failover'],['key'=>'cooldown_seconds','type'=>'integer','default'=>300]],
 'migrations'=>['2026_10_07_020001_create_mailer_smtp_profiles.php','2026_10_07_020003_harden_mailer_smtp_profiles.php'],
 'web_route_files'=>['addons/mailer-smtp/routes/admin.php'],'api_route_files'=>[],
 'routes'=>['/admin/mailer-smtp'],'api_routes'=>[],
 'capabilities'=>['unlimited_smtp_profiles','priority_failover','round_robin','health_tracking','safe_test_send','encrypted_credentials','core_mail_integration'],
];