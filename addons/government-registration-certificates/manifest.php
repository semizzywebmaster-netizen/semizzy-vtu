<?php
return [
 'identifier'=>'government.registration-certificates',
 'name'=>'Government Registration & Certificates',
 'version'=>'0.1.0',
 'description'=>'Admin-controlled government registration, application and certificate services with API and manual fulfillment.',
 'compatibility'=>'>=2.0.0',
 'dependencies'=>[],
 'permissions'=>[
  'government.view','government.orders.manage','government.services.manage','government.documents.manage','government.settings.manage'
 ],
 'role_permissions'=>[
  'ADMIN'=>['government.view','government.orders.manage','government.services.manage','government.documents.manage','government.settings.manage'],
  'STAFF'=>['government.view','government.orders.manage','government.services.manage','government.documents.manage'],
  'SUPPORT'=>['government.view','government.orders.manage'],
  'USER'=>['government.view','government.orders.manage'],
 ],
 'navigation'=>[['id'=>'government-services','label'=>'Government Services','url'=>'/government-services','icon'=>'building','permission'=>'government.view','section'=>'services','order'=>160]],
 'admin_navigation'=>[['id'=>'admin-government-services','label'=>'Government Services','url'=>'/admin/government-services','icon'=>'building','permission'=>'government.view','section'=>'addons','order'=>160]],
 'settings'=>[
  ['key'=>'enabled','type'=>'boolean','default'=>true],
  ['key'=>'fulfillment_mode','type'=>'string','default'=>'api_or_manual'],
  ['key'=>'require_document_review','type'=>'boolean','default'=>true],
 ],
 'migrations'=>['2026_10_07_004000_create_government_services_tables.php'],
 'web_route_files'=>['addons/government-registration-certificates/routes/web.php','addons/government-registration-certificates/routes/admin.php'],
 'api_route_files'=>['addons/government-registration-certificates/routes/api.php'],
 'routes'=>['/government-services','/admin/government-services'],
 'api_routes'=>['/api/v1/government-services'],
 'provider_integrations'=>['Core ProviderManager'],
 'provider_capabilities'=>['government_application_submit','government_application_status','government_certificate_retrieve'],
 'capabilities'=>['service_catalog','applications','documents','api_fulfillment','manual_fulfillment','status_requery','certificate_records','admin_service_management'],
];