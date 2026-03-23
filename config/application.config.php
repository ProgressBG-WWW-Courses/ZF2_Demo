<?php
/**
 * Application configuration
 *
 * Lists the modules to load and the paths to search for modules.
 */
return array(
    // List of modules to load
    'modules' => array(
        'Application',
        'Room',
        'Auth',
    ),

    'module_listener_options' => array(
        // Paths where modules live
        'module_paths' => array(
            './module',
            './vendor',
        ),

        // Glob pattern for additional config files to merge
        'config_glob_paths' => array(
            'config/autoload/{,*.}{global,local}.php',
        ),
    ),
);
