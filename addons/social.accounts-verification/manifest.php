<?php
return [
 'identifier'=>'social.accounts-verification','name'=>'Social Accounts & Verification','version'=>'0.1.0',
 'description'=>'Social account linking, ownership verification and verification status management.',
 'compatibility'=>'>=2.0.0','dependencies'=>[],
 'permissions'=>['social.view','social.accounts.manage','social.verification.manage','social.requery','social.settings.manage'],
 'role_permissions'=>[
  'ADMIN'=>['social.view','social.accounts.manage','social.verification.manage','social.requery','social.settings.manage'],
  'STAFF'=>['social.view','social.accounts.manage','social.requery'],
  'SUPPORT'=>['social.view','social.requery'],
  'USER'=>['social.view','social.accounts.manage','social.requery'],
 ],
 'navigation'=>[['id'=>'social-accounts','label'=>'Social Accounts','url'=>'/social','icon'=>'share','permission'=>'social.view','section'=>'services','order'=>150]],
 'admin_navigation'=>[['id'=>'admin-social-accounts','label'=>'Social Accounts','url'=>'/admin/social','icon'=>'share','permission'=>'social.view','section'=>'addons','order'=>150]],
 'settings'=>[
  ['key'=>'enabled','type'=>'boolean','default'=>true],
  ['key'=>'verification_mode','type'=>'string','default'=>'provider_or_manual'],
 ],
 'migrations'=>['2026_10_07_001600_create_social_accounts_tables.php'],
 'web_route_files'=>['addons/social.accounts-verification/routes/web.php','addons/social.accounts-verification/routes/admin.php'],
 'api_route_files'=>['addons/social.accounts-verification/routes/api.php'],
 'routes'=>['/social','/admin/social'],'api_routes'=>['/api/v1/social'],
 'provider_integrations'=>['Core ProviderManager'],
 'provider_capabilities'=>['social_account_verify','social_account_status','social_account_profile'],
 'capabilities'=>['account_linking','ownership_verification','verification_status','provider_mapping','requery','manual_review','audit_trail'],
];