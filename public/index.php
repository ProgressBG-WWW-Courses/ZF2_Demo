<?php
/**
 * ZF2 Hello World - Entry Point
 *
 * All requests come through this file.
 */

chdir(dirname(__DIR__));

// Support PHP built-in web server
if (php_sapi_name() === 'cli-server') {
    $path = realpath(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if (is_string($path) && __DIR__ !== $path && file_exists($path)) {
        return false;
    }
}

// Load Composer autoloader
require 'vendor/autoload.php';

// Boot and run the ZF2 MVC application
Zend\Mvc\Application::init(require 'config/application.config.php')->run();
