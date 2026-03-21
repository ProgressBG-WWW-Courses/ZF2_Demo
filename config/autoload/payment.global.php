<?php
/**
 * Payment configuration — reads Revolut credentials from .env
 *
 * This file is auto-loaded by ZF2 via the config_glob_paths setting
 * in application.config.php.  Values are merged into the global Config
 * service and consumed by PaymentServiceFactory.
 */

// Simple .env loader — no third-party dependency required.
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        // Don't overwrite values already set in the real environment
        if (!isset($_ENV[$key]) && getenv($key) === false) {
            putenv("{$key}={$value}");
        }
    }
}

return [
    'payment' => [
        'api_url'        => getenv('REVOLUT_API_URL')        ?: 'https://sandbox-merchant.revolut.com',
        'secret_key'     => getenv('REVOLUT_API_SECRET_KEY') ?: '',
        'public_key'     => getenv('REVOLUT_API_PUBLIC_KEY') ?: '',
        'webhook_secret' => getenv('REVOLUT_WEBHOOK_SECRET') ?: '',
        'environment'    => getenv('REVOLUT_ENVIRONMENT')    ?: 'sandbox',
        'public_url'     => getenv('APP_PUBLIC_URL') ?: '',
        'db' => [
            'host'     => getenv('DB_HOST')     ?: 'db',
            'port'     => getenv('DB_PORT')     ?: '3306',
            'dbname'   => getenv('DB_NAME')     ?: 'hotel_db',
            'user'     => getenv('DB_USER')     ?: 'hotel_user',
            'password' => getenv('DB_PASSWORD') ?: 'hotel_pass',
        ],
    ],
];
