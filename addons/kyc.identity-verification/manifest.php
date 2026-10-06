<?php

return [
    'identifier' => 'kyc.identity-verification',
    'name' => 'KYC & Identity Verification',
    'version' => '1.0.0',
    'compatibility' => '>=2.0.0',
    'dependencies' => [],
    'role_permissions' => [
        'ADMIN' => ['kyc.view', 'kyc.submit', 'kyc.verify', 'kyc.review', 'kyc.documents.manage', 'kyc.providers.manage', 'kyc.settings.manage'],
        'STAFF' => ['kyc.view', 'kyc.review', 'kyc.documents.manage'],
        'SUPPORT' => ['kyc.view'],
        'USER' => ['kyc.view', 'kyc.submit', 'kyc.verify'],
    ],
    'permissions' => [
        'kyc.view',
        'kyc.submit',
        'kyc.verify',
        'kyc.review',
        'kyc.documents.manage',
        'kyc.providers.manage',
        'kyc.settings.manage',
    ],
    'navigation' => [
        [
            'id' => 'kyc',
            'label' => 'KYC & Verification',
            'url' => '/admin/kyc',
            'icon' => 'shield-check',
            'permission' => 'kyc.view',
            'section' => 'addons',
            'order' => 25,
        ],
    ],
    'settings' => [
        ['key' => 'document_retention_days', 'type' => 'integer', 'default' => 365],
        ['key' => 'auto_reject_after_days', 'type' => 'integer', 'default' => 30],
        ['key' => 'require_document', 'type' => 'boolean', 'default' => true],
    ],
    'migrations' => [
        '2026_10_06_000200_create_kyc_applications.php',
        '2026_10_06_000201_create_kyc_documents.php',
        '2026_10_06_000202_create_kyc_verifications.php',
        '2026_10_06_000203_add_kyc_verification_tracking.php',
    ],
    'web_route_files' => [
        'addons/kyc.identity-verification/routes/web.php',
        'addons/kyc.identity-verification/routes/admin.php',
    ],
    'api_route_files' => [
        'addons/kyc.identity-verification/routes/api.php',
    ],
    'routes' => ['/kyc'],
    'api_routes' => ['/api/v1/kyc'],
    'services' => [
        'identity verification',
        'KYC application workflow',
        'document review',
        'user tier verification',
    ],
    'provider_integrations' => ['Core ProviderManager'],
    'scheduled_tasks' => ['KYC review/retention maintenance'],
    'events' => [
        'kyc.application.submitted',
        'kyc.application.approved',
        'kyc.application.rejected',
    ],
];
