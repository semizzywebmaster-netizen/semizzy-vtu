<?php
return [
 'identifier'=>'business.agent-merchant-reseller','autoload_namespace'=>'Addons\\BusinessAgentMerchantReseller','commercial_adapters'=>['Addons\\BusinessAgentMerchantReseller\\Services\\BusinessCommercialAdapter'],'name'=>'Business, Agent, Merchant & Reseller','version'=>'1.0.0',
 'description'=>'Configurable business accounts, agents, merchants and resellers with tiered commercial controls, pricing profiles and transaction limits.',
 'core_compatibility'=>'>=2.0.0','dependencies'=>[],
 'permissions'=>['business.view','business.manage','business.approve','business.agents.manage','business.merchants.manage','business.resellers.manage','business.pricing.manage','business.limits.manage','business.audit'],
 'role_permissions'=>[
  'ADMIN'=>['business.view','business.manage','business.approve','business.agents.manage','business.merchants.manage','business.resellers.manage','business.pricing.manage','business.limits.manage','business.audit'],
  'STAFF'=>['business.view','business.manage','business.approve','business.agents.manage','business.merchants.manage','business.resellers.manage'],
  'SUPPORT'=>['business.view'],
  'USER'=>['business.view'],
 ],
 'navigation'=>[['id'=>'business-portal','label'=>'Business & Reseller','url'=>'/business','icon'=>'briefcase','permission'=>'business.view','section'=>'services','order'=>92]],
 'admin_navigation'=>[['id'=>'admin-business','label'=>'Business & Agent Management','url'=>'/admin/business','icon'=>'briefcase','permission'=>'business.view','section'=>'addons','order'=>92]],
 'settings'=>[
  ['key'=>'enabled','type'=>'boolean','default'=>true],
  ['key'=>'merchant_enabled','type'=>'boolean','default'=>true],
  ['key'=>'agent_enabled','type'=>'boolean','default'=>true],
  ['key'=>'reseller_enabled','type'=>'boolean','default'=>true],
  ['key'=>'approval_required','type'=>'boolean','default'=>true],
 ],
 'migrations'=>['2026_10_07_070000_create_business_agent_merchant_reseller_tables.php'],
 'web_route_files'=>['addons/business-agent-merchant-reseller/routes/web.php','addons/business-agent-merchant-reseller/routes/admin.php'],
 'api_route_files'=>['addons/business-agent-merchant-reseller/routes/api.php'],
 'provider_integrations'=>['Core ProviderManager','Core wallet/ledger','Core users','Core KYC','Core audit'],
 'provider_capabilities'=>[],
 'capabilities'=>['business_profiles','agent_profiles','merchant_profiles','reseller_profiles','approval_workflow','pricing_profiles','transaction_limits','commission_rules','status_controls','audit_metadata'],
];