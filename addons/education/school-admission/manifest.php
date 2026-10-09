<?php

return [
    'identifier' => 'education.school-admission',
    'name' => 'School Admission Services',
    'version' => '1.0.0',
    'description' => 'Configurable school and institution admission products, application-form services, screening fees, acceptance fees and provider-backed admission transactions.',
    'compatibility' => '>=2.0.0',
    'dependencies' => [],
    'autoload_namespace' => 'Semizzy\\Addons\\SchoolAdmission',
    'permissions' => [
        'school-admission.view',
        'school-admission.manage',
        'school-admission.institutions.manage',
        'school-admission.products.manage',
        'school-admission.transactions.view',
        'school-admission.settings.manage',
    ],
    'role_permissions' => [
        'ADMIN' => ['school-admission.view','school-admission.manage','school-admission.institutions.manage','school-admission.products.manage','school-admission.transactions.view','school-admission.settings.manage'],
        'STAFF' => ['school-admission.view','school-admission.manage','school-admission.products.manage','school-admission.transactions.view'],
        'SUPPORT' => ['school-admission.view','school-admission.transactions.view'],
        'USER' => ['school-admission.view'],
    ],
    'navigation' => [[
        'id' => 'school-admission', 'label' => 'School Admission', 'url' => '/school-admission',
        'icon' => 'graduation-cap', 'permission' => 'school-admission.view', 'section' => 'services', 'order' => 91,
    ]],
    'admin_navigation' => [[
        'id' => 'admin-school-admission', 'label' => 'School Admission', 'url' => '/admin/school-admission',
        'icon' => 'graduation-cap', 'permission' => 'school-admission.view', 'section' => 'addons', 'order' => 91,
    ]],
    'settings' => [
        ['key' => 'enabled', 'type' => 'boolean', 'default' => true],
        ['key' => 'default_currency', 'type' => 'string', 'default' => 'NGN'],
        ['key' => 'require_transaction_pin', 'type' => 'boolean', 'default' => true],
        ['key' => 'allow_unlisted_institutions', 'type' => 'boolean', 'default' => false],
    ],
    'migrations' => ['2026_10_09_000001_create_school_admission_tables.php'],
    'web_route_files' => ['addons/education/school-admission/routes/web.php','addons/education/school-admission/routes/admin.php'],
    'provider_integrations' => ['Core ProviderManager'],
    'provider_capabilities' => ['transaction_initiation','transaction_status'],
    'events' => ['school_admission.product.created','school_admission.transaction.created','school_admission.transaction.completed','school_admission.transaction.failed'],
];