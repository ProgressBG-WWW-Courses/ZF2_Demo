<?php
return array(
    // Register services — UserService and AclService
    'service_manager' => array(
        'invokables' => array(
            'UserService' => 'Auth\Service\UserService',
            'AclService'  => 'Auth\Service\AclService',
        ),
    ),

    // Register the controller using a factory (Dependency Injection)
    'controllers' => array(
        'factories' => array(
            'Auth\Controller\Auth' => 'Auth\Factory\AuthControllerFactory',
        ),
    ),

    // Route configuration — login and logout
    'router' => array(
        'routes' => array(
            // Parent route: /auth
            'auth' => array(
                'type'    => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/auth',
                    'defaults' => array(
                        'controller' => 'Auth\Controller\Auth',
                        'action'     => 'login',
                    ),
                ),
                'may_terminate' => true,

                'child_routes' => array(
                    // /auth/login
                    'login' => array(
                        'type'    => 'Zend\Mvc\Router\Http\Literal',
                        'options' => array(
                            'route'    => '/login',
                            'defaults' => array(
                                'action' => 'login',
                            ),
                        ),
                    ),
                    // /auth/logout
                    'logout' => array(
                        'type'    => 'Zend\Mvc\Router\Http\Literal',
                        'options' => array(
                            'route'    => '/logout',
                            'defaults' => array(
                                'action' => 'logout',
                            ),
                        ),
                    ),
                ),
            ),
        ),
    ),

    // View manager — tell ZF2 where to find our .phtml templates
    'view_manager' => array(
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
    ),
);
