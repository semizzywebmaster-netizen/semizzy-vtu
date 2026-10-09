<?php
return [
 'identifier'=>'loans.credit','name'=>'Loans & Credit','version'=>'1.0.0',
 'description'=>'Configurable wallet-backed lending with schedules, repayments and immutable loan ledger.',
 'core_compatibility'=>'>=2.0.0','dependencies'=>[],
 'permissions'=>['loans.view','loans.apply','loans.manage','loans.approve','loans.repay','loans.settings.manage'],
 'role_permissions'=>[
  'ADMIN'=>['loans.view','loans.apply','loans.manage','loans.approve','loans.repay','loans.settings.manage'],
  'STAFF'=>['loans.view','loans.manage','loans.approve'],
  'SUPPORT'=>['loans.view'],'USER'=>['loans.view','loans.apply','loans.repay'],
 ],
 'navigation'=>[['id'=>'loans','label'=>'Loans','url'=>'/loans','icon'=>'banknote','permission'=>'loans.view','section'=>'services','order'=>50]],
 'admin_navigation'=>[['id'=>'admin-loans','label'=>'Loans','url'=>'/admin/loans','icon'=>'banknote','permission'=>'loans.view','section'=>'addons','order'=>50]],
 'settings'=>['enabled'=>true,'default_currency'=>'NGN','minimum_amount_minor'=>1000,'maximum_amount_minor'=>100000000,'default_interest_rate'=>10,'default_tenure_days'=>30,'late_penalty_rate'=>1],
 'migrations'=>[
  '2026_10_06_000500_create_loan_products.php',
  '2026_10_06_000501_create_loans.php',
  '2026_10_06_000502_create_loan_repayments.php',
  '2026_10_06_000503_seed_default_loan_products.php',
  '2026_10_09_130100_harden_loan_repayment_retention.php',
 ],
 'web_route_files'=>['addons/loans.credit/routes/web.php','addons/loans.credit/routes/admin.php'],
 'api_route_files'=>['addons/loans.credit/routes/api.php'],
 'provider_integration'=>['type'=>'core','manager'=>'App\\Services\\Providers\\ProviderManager','capabilities'=>[]],
 'scheduled_tasks'=>['overdue'=>'Process overdue loan schedules through database queue/cPanel cron.'],
 'events'=>['loan.applied','loan.approved','loan.rejected','loan.disbursed','loan.repaid','loan.overdue'],
];