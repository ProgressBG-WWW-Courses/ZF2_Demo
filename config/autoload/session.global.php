<?php
/**
 * Global Session Configuration
 *
 * This configuration sets up the SessionManager with best-practice security flags:
 * - HttpOnly: Prevents JavaScript from accessing the session cookie.
 * - Secure: Ensures cookies are only sent over HTTPS (disabled by default for local dev).
 * - SameSite: Mitigates CSRF attacks by restricting cookie transmission.
 */

use Zend\Session\Storage\SessionArrayStorage;
use Zend\Session\Validator\HttpUserAgent;
use Zend\Session\Validator\RemoteAddr;

return [
    'session_config' => [
        // Prevents JavaScript from accessing the session cookie
        'cookie_httponly' => true,

        // Only send cookie over HTTPS. 
        // Set to true in production/local HTTPS environments.
        'cookie_secure' => false, 

        // Mitigates CSRF. Lax is a good balance for most apps.
        'cookie_samesite' => 'Lax',

        // Session timeout (optional, e.g., 1 hour)
        'gc_maxlifetime' => 3600,
    ],
    'session_storage' => [
        'type' => SessionArrayStorage::class,
    ],
    'session_validators' => [
        HttpUserAgent::class,
        RemoteAddr::class,
    ],
    'service_manager' => [
        'factories' => [
            // Register the ZF2 Session Manager factory
            'Zend\Session\SessionManager' => 'Zend\Session\Service\SessionManagerFactory',
            'Zend\Session\Config\ConfigInterface' => 'Zend\Session\Service\SessionConfigFactory',
        ],
    ],
];
