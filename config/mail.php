<?php

// Symfony Mailer accepts "smtp" and "smtps" as transport schemes.
// Older deployments may still store "tls" or "ssl" as the scheme;
// normalize those values while preserving STARTTLS/implicit-TLS behavior.
$mailScheme = env('MAIL_SCHEME');
if ($mailScheme === 'tls') {
    $mailScheme = 'smtp';
} elseif ($mailScheme === 'ssl') {
    $mailScheme = 'smtps';
} elseif (! in_array($mailScheme, ['smtp', 'smtps'], true)) {
    $mailScheme = null;
}

$mailUrl = env('MAIL_URL');
if (is_string($mailUrl)) {
    $mailUrl = preg_replace('~^tls://~i', 'smtp://', $mailUrl);
    $mailUrl = preg_replace('~^ssl://~i', 'smtps://', $mailUrl);
}

return [
    'default' => env('MAIL_MAILER', 'log'),
    'mailers' => [
        'log' => ['transport' => 'log', 'channel' => env('MAIL_LOG_CHANNEL')],
        'array' => ['transport' => 'array'],
        'smtp' => [
            'transport' => 'smtp',
            'scheme' => $mailScheme,
            'url' => $mailUrl,
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],
    ],
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'SEMIZZY ONE')),
    ],
];
