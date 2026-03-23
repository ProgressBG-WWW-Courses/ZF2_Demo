<?php
/**
 * Payment configuration — Revolut Merchant API credentials.
 *
 * Environment variables are loaded by db.global.php (which runs first).
 * This file is auto-loaded by ZF2 via the config_glob_paths setting
 * in application.config.php. Values are merged into the global Config
 * service and consumed by PaymentServiceFactory.
 */
return [
    'payment' => [
        'api_url'        => getenv('REVOLUT_API_URL')        ?: 'https://sandbox-merchant.revolut.com',
        'secret_key'     => getenv('REVOLUT_API_SECRET_KEY') ?: '',
        'public_key'     => getenv('REVOLUT_API_PUBLIC_KEY') ?: '',
        'webhook_secret' => getenv('REVOLUT_WEBHOOK_SECRET') ?: '',
        'environment'    => getenv('REVOLUT_ENVIRONMENT')    ?: 'sandbox',
        'public_url'     => getenv('APP_PUBLIC_URL') ?: '',
    ],
];
