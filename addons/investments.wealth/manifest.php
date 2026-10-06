<?php
return [
 'identifier'=>'investments.wealth','name'=>'Investments & Wealth','version'=>'1.0.0',
 'description'=>'Configurable wallet-backed investment products with funding, maturity, profit and redemption.',
 'core_compatibility'=>'>=2.0.0','dependencies'=>[],
 'permissions'=>['investments.view','investments.create','investments.manage','investments.redeem','investments.settings.manage'],
 'role_permissions'=>[
  'ADMIN'=>['investments.view','investments.create','investments.manage','investments.redeem','investments.settings.manage'],
  'STAFF'=>['investments.view','investments.manage'],'SUPPORT'=>['investments.view'],
  'USER'=>['investments.view','investments.create','investments.redeem'],
 ],
 'navigation'=>[['id'=>'investments','label'=>'Investments','url'=>'/investments','icon'=>'trending-up','permission'=>'investments.view','section'=>'services','order'=>60]],
 'admin_navigation'=>[['id'=>'admin-investments','label'=>'Investments','url'=>'/admin/investments','icon'=>'trending-up','permission'=>'investments.view','section'=>'addons','order'=>60]],
 'settings'=>['enabled'=>true,'default_currency'=>'NGN','minimum_amount_minor'=>1000,'maximum_amount_minor'=>1000000000,'default_profit_rate'=>10,'default_term_days'=>90,'early_redemption_enabled'=>true],
 'migrations'=>[
  '2026_10_06_000600_create_investment_products.php',
  '2026_10_06_000601_create_investment_accounts.php',
  '2026_10_06_000602_create_investment_movements.php',
  '2026_10_06_000603_seed_default_investment_products.php',
  '2026_10_06_000604_add_investment_creation_idempotency.php',
 ],
 'web_route_files'=>['addons/investments.wealth/routes/web.php','addons/investments.wealth/routes/admin.php'],
 'api_route_files'=>['addons/investments.wealth/routes/api.php'],
 'provider_integration'=>['type'=>'core','manager'=>'App\\Services\\Providers\\ProviderManager','capabilities'=>[]],
 'scheduled_tasks'=>['maturity'=>'Process investment maturity/profit and scheduled redemption through database queue/cPanel cron.'],
 'events'=>['investment.created','investment.funded','investment.matured','investment.redeemed'],
];