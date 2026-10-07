<?php
return [
 'identifier'=>'spin.to-win','name'=>'Spin to Win / Rewards Game','version'=>'1.0.0',
 'description'=>'Server-side weighted spin campaigns with idempotent play records and approval-safe rewards.',
 'compatibility'=>'>=2.0.0','dependencies'=>['rewards.referrals-promotions'],
 'permissions'=>['spin.view','spin.play','spin.manage','spin.settings.manage','spin.audit'],
 'role_permissions'=>['ADMIN'=>['spin.view','spin.play','spin.manage','spin.settings.manage','spin.audit'],'STAFF'=>['spin.view','spin.manage','spin.audit'],'SUPPORT'=>['spin.view','spin.audit'],'USER'=>['spin.view','spin.play']],
 'navigation'=>[['id'=>'spin-to-win','label'=>'Spin to Win','url'=>'/spin-to-win','icon'=>'gift','permission'=>'spin.view','section'=>'services','order'=>180]],
 'admin_navigation'=>[['id'=>'admin-spin-to-win','label'=>'Spin to Win','url'=>'/admin/spin-to-win','icon'=>'gift','permission'=>'spin.manage','section'=>'addons','order'=>180]],
 'settings'=>[['key'=>'enabled','type'=>'boolean','default'=>true],['key'=>'default_daily_limit','type'=>'integer','default'=>1],['key'=>'reward_requires_admin_approval','type'=>'boolean','default'=>true]],
 'migrations'=>['2026_10_07_020000_create_spin_to_win_tables.php','2026_10_07_020002_harden_spin_to_win.php'],
 'web_route_files'=>['addons/spin-to-win/routes/web.php','addons/spin-to-win/routes/admin.php'],'api_route_files'=>[],
 'routes'=>['/spin-to-win','/admin/spin-to-win'],'api_routes'=>[],
 'capabilities'=>['campaigns','weighted_prizes','server_side_randomization','daily_limits','total_limits','tier_eligibility','idempotency','reward_approval','audit_history'],
];