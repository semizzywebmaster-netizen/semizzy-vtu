<?php
return [
 'identifier'=>'exams.results','name'=>'Exams & Result Checking','version'=>'1.0.0',
 'description'=>'Provider-driven examination result checking and result-token services with secure wallet billing, status reconciliation and failover.',
 'compatibility'=>'>=2.0.0','dependencies'=>[],
 'permissions'=>['exams.view','exams.check','exams.manage','exams.providers.manage','exams.products.manage','exams.transactions.view','exams.settings.manage'],
 'role_permissions'=>[
  'ADMIN'=>['exams.view','exams.check','exams.manage','exams.providers.manage','exams.products.manage','exams.transactions.view','exams.settings.manage'],
  'STAFF'=>['exams.view','exams.check','exams.manage','exams.products.manage','exams.transactions.view'],
  'SUPPORT'=>['exams.view','exams.transactions.view'],
  'USER'=>['exams.view','exams.check','exams.transactions.view'],
 ],
 'navigation'=>[['id'=>'exams-results','label'=>'Exams & Results','url'=>'/exams/results','icon'=>'graduation-cap','permission'=>'exams.view','section'=>'services','order'=>90]],
 'admin_navigation'=>[['id'=>'admin-exams-results','label'=>'Exams & Results','url'=>'/admin/exams/results','icon'=>'graduation-cap','permission'=>'exams.view','section'=>'addons','order'=>90]],
 'settings'=>[
  ['key'=>'enabled','type'=>'boolean','default'=>true],
  ['key'=>'default_currency','type'=>'string','default'=>'NGN'],
  ['key'=>'max_checks_per_request','type'=>'integer','default'=>1],
  ['key'=>'require_transaction_pin','type'=>'boolean','default'=>true],
 ],
 'migrations'=>[
  '2026_10_08_000900_create_exam_products.php',
  '2026_10_08_000901_create_exam_transactions.php',
 ],
 'web_route_files'=>['addons/exams.results/routes/web.php','addons/exams.results/routes/admin.php'],
 'api_route_files'=>['addons/exams.results/routes/api.php'],
 'routes'=>['/exams/results'],'api_routes'=>['/api/v1/exams/results'],
 'provider_integrations'=>['Core ProviderManager'],
 'provider_capabilities'=>['transaction_initiation','transaction_status'],
 'scheduled_tasks'=>['Examination result transaction reconciliation'],
 'events'=>['exam.result.check.created','exam.result.check.completed','exam.result.check.failed'],
];
