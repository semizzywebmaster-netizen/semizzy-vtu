<?php
return [
 'identifier'=>'education','name'=>'Education & Past Questions','category'=>'Education & Student Services','version'=>'1.0.0',
 'description'=>'School past questions and national examination past questions with private document storage, searchable catalogues, admin publishing and wallet-backed paid downloads.',
 'compatibility'=>'>=2.0.0','dependencies'=>[],
 'permissions'=>['education.view','education.download','education.purchase','education.content.manage','education.transactions.view'],
 'role_permissions'=>[
  'ADMIN'=>['education.view','education.download','education.purchase','education.content.manage','education.transactions.view'],
  'STAFF'=>['education.view','education.download','education.content.manage','education.transactions.view'],
  'SUPPORT'=>['education.view','education.transactions.view'],
  'USER'=>['education.view','education.download','education.purchase'],
 ],
 'navigation'=>[
  ['id'=>'school-past-questions','label'=>'School Past Questions','url'=>'/education/school-past-questions','icon'=>'school','permission'=>'education.view','section'=>'services','order'=>100],
  ['id'=>'exam-past-questions','label'=>'Exam Past Questions','url'=>'/education/exam-past-questions','icon'=>'book-open','permission'=>'education.view','section'=>'services','order'=>101],
 ],
 'admin_navigation'=>[
  ['id'=>'education-past-questions-admin','label'=>'Education Library','url'=>'/admin/education/past-questions','icon'=>'graduation-cap','permission'=>'education.content.manage','section'=>'addons','order'=>100],
 ],
 'settings'=>[
  ['key'=>'enabled','type'=>'boolean','default'=>true],
  ['key'=>'default_currency','type'=>'string','default'=>'NGN'],
  ['key'=>'max_upload_size_mb','type'=>'integer','default'=>20],
 ],
 'migrations'=>['2026_10_09_110000_create_education_past_question_library.php','2026_10_09_110100_create_and_import_education_reference_catalogue.php'],
 'web_route_files'=>['addons/education/routes/web.php','addons/education/routes/admin.php'],
 'routes'=>['/education/school-past-questions','/education/exam-past-questions'],
 'provider_integrations'=>['Core ProviderManager (existing education service only)'],
 'events'=>['education.content.published','education.content.downloaded','education.content.purchased'],
];
