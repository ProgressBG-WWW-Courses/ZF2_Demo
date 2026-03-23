<?php
/**
 * Shared database configuration — Doctrine ORM connection.
 *
 * Also loads .env so that all subsequent config files can use getenv().
 * This file loads first alphabetically (db < payment), ensuring
 * environment variables are available to every config file.
 *
 * Doctrine ORM Module reads the 'doctrine.connection.orm_default' key
 * to create the EntityManager automatically.
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
    'doctrine' => [
        'connection' => [
            'orm_default' => [
                'driverClass' => 'Doctrine\DBAL\Driver\PDOMySql\Driver',
                'params'      => [
                    'host'     => getenv('DB_HOST')     ?: 'db',
                    'port'     => getenv('DB_PORT')     ?: '3306',
                    'dbname'   => getenv('DB_NAME')     ?: 'hotel_db',
                    'user'     => getenv('DB_USER')     ?: 'hotel_user',
                    'password' => getenv('DB_PASSWORD') ?: 'hotel_pass',
                    'charset'  => 'utf8mb4',
                ],
            ],
        ],
    ],
];
