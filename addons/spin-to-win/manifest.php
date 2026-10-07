<?php
return [
 'identifier'=>'spin.to-win',
 'name'=>'Spin to Win',
 'version'=>'0.1.0',
 'description'=>'Configurable spin-to-win campaigns with weighted prizes, eligibility rules, limits and admin-controlled rewards.',
 'compatibility'=>'>=2.0.0','dependencies'=>[],
 'permissions'=>['spin.view','spin.play','spin.manage','spin.settings.manage'],
 'role_permissions'=>[
  'ADMIN'=>['spin.view','spin.play','spin.manage','spin.settings.manage'],
  'STAFF'=>['spin.view','spin.manage'],
  'SUPPORT'=>['spin.view'],
  'USER'=>['spin.view','spin.play'],
 ],
 'navigation'=>[['id'=>'spin-to-win','label'=>'Spin to Win','url'=>'/spin-to-win','icon'=>'sparkles','permission'=>'spin.view','section'=>'services','order'=>175]],
 'admin_navigation'=>[['id'=>'admin-spin-to-win','label'=>'Spin to Win','url'=>'/admin/spin-to-win','icon'=>'sparkles','permission'=>'spin.manage','section'=>'addons','order'=>175]],
 'settings'=>[
  ['key'=>'enabled','type'=>'boolean','default'=>true],
  ['key'=>'require_transaction_pin','type'=>'boolean','default'=>true],
  ['key'=>'max_daily_plays','type'=>'integer','default'=>1],
  ['key'=>'reward_requires_admin_approval','type'=>'boolean','default'=>true],
 ],
 'migrations'=>['2026_10_07_020000_create_spin_to_win_tables.php'],
 'web_route_files'=>['addons/spin-to-win/routes/web.php','addons/spin-to-win/routes/admin.php'],
 'api_route_files'=>['addons/spin-to-win/routes/api.php'],
 'routes'=>['/spin-to-win','/admin/spin-to-win'],'api_routes'=>['/api/v1/spin-to-win'],
 'provider_integrations'=>[],'provider_capabilities'=>[],
 'capabilities'=>['spin_campaigns','weighted_prizes','eligibility_rules','play_limits','wallet_rewards','admin_approval','audit_metadata'],
];