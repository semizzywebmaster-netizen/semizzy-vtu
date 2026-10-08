<?php
return [
 'identifier'=>'investments.wealth','name'=>'Stocks & Investments Marketplace','version'=>'1.1.0',
 'description'=>'Investment products plus a regulated-boundary marketplace catalogue for securities, market data, broker execution, portfolios and corporate actions.',
 'core_compatibility'=>'>=2.0.0','dependencies'=>[],
 'permissions'=>['investments.view','investments.create','investments.manage','investments.redeem','investments.settings.manage','investments.market.view','investments.market.manage','investments.orders.manage'],
 'role_permissions'=>[
  'ADMIN'=>['investments.view','investments.create','investments.manage','investments.redeem','investments.settings.manage','investments.market.view','investments.market.manage','investments.orders.manage'],
  'STAFF'=>['investments.view','investments.manage','investments.market.view','investments.market.manage'],
  'SUPPORT'=>['investments.view','investments.market.view'],
  'USER'=>['investments.view','investments.create','investments.redeem','investments.market.view'],
 ],
 'navigation'=>[['id'=>'investments','label'=>'Stocks & Investments','url'=>'/investments','icon'=>'trending-up','permission'=>'investments.view','section'=>'services','order'=>60]],
 'admin_navigation'=>[['id'=>'admin-investments','label'=>'Stocks & Investments','url'=>'/admin/investments','icon'=>'trending-up','permission'=>'investments.view','section'=>'addons','order'=>60]],
 'settings'=>[
  'enabled'=>true,'default_currency'=>'NGN','minimum_amount_minor'=>1000,'maximum_amount_minor'=>1000000000,
  'default_profit_rate'=>10,'default_term_days'=>90,'early_redemption_enabled'=>true,
  'marketplace_enabled'=>true,'trading_enabled'=>false,'market_data_enabled'=>false,
  'require_verified_provider'=>true,'require_trade_kyc'=>true,
 ],
 'migrations'=>[
  '2026_10_06_000600_create_investment_products.php',
  '2026_10_06_000601_create_investment_accounts.php',
  '2026_10_06_000602_create_investment_movements.php',
  '2026_10_06_000603_seed_default_investment_products.php',
  '2026_10_06_000604_add_investment_creation_idempotency.php',
  '2026_10_08_004000_create_stock_marketplace_schema.php',
  '2026_10_08_004001_add_provider_provenance_to_market_data.php',
 ],
 'web_route_files'=>['addons/investments.wealth/routes/web.php','addons/investments.wealth/routes/admin.php'],
 'api_route_files'=>['addons/investments.wealth/routes/api.php'],
 'provider_integration'=>['type'=>'core','manager'=>'App\Services\Providers\ProviderManager','capabilities'=>['market_data','order_execution','portfolio_sync']],
 'scheduled_tasks'=>[
  'maturity'=>'Process investment maturity/profit and scheduled redemption through database queue/cPanel cron.',
  'market_quotes'=>'Refresh market quotes only when a verified market-data provider is configured.',
  'portfolio_reconciliation'=>'Reconcile broker executions and holdings through database queue/cPanel cron.',
 ],
 'events'=>['investment.created','investment.funded','investment.matured','investment.redeemed','investment.order.created','investment.order.executed','investment.holding.reconciled'],
];
