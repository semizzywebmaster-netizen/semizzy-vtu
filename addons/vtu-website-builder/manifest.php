<?php
return [
 'identifier'=>'vtu.website-builder',
 'autoload_namespace'=>'Addons\\VtuWebsiteBuilder',
 'name'=>'VTU Website Builder',
 'version'=>'1.0.0',
 'description'=>'No-code website builder for users, merchants and businesses with templates, pages, domains and versioned publishing.',
 'core_compatibility'=>'>=2.0.0','dependencies'=>[],
 'permissions'=>['website.view','website.manage','website.publish','website.domains.manage','website.themes.manage','website.audit'],
 'role_permissions'=>[
  'ADMIN'=>['website.view','website.manage','website.publish','website.domains.manage','website.themes.manage','website.audit'],
  'STAFF'=>['website.view','website.manage','website.publish','website.domains.manage'],
  'SUPPORT'=>['website.view'],'USER'=>['website.view','website.manage','website.publish','website.domains.manage'],
 ],
 'navigation'=>[['id'=>'website-builder','label'=>'Website Builder','url'=>'/website-builder','icon'=>'globe','permission'=>'website.view','section'=>'services','order'=>93]],
 'admin_navigation'=>[['id'=>'admin-website-builder','label'=>'Website Builder','url'=>'/admin/website-builder','icon'=>'globe','permission'=>'website.view','section'=>'addons','order'=>93]],
 'settings'=>[
  ['key'=>'enabled','type'=>'boolean','default'=>true],
  ['key'=>'publishing_enabled','type'=>'boolean','default'=>true],
  ['key'=>'custom_domains_enabled','type'=>'boolean','default'=>true],
  ['key'=>'subdomains_enabled','type'=>'boolean','default'=>true],
 ],
 'migrations'=>['2026_10_07_080000_create_website_builder_tables.php'],
 'web_route_files'=>['addons/vtu-website-builder/routes/web.php','addons/vtu-website-builder/routes/admin.php'],
 'api_route_files'=>['addons/vtu-website-builder/routes/api.php'],
 'provider_integrations'=>['Core users','Core authentication','Core media/storage','Core notifications','Core audit'],
 'provider_capabilities'=>[],
 'capabilities'=>['site_creation','template_catalog','page_builder','draft_publishing','revision_history','custom_domains','subdomains','seo_metadata','contact_forms','analytics_hooks','audit_metadata'],
];