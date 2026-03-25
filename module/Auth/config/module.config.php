<?php
return array(
    // Register services — UserService (via factory for EntityManager injection) and AclService
    'service_manager' => array(
        'factories' => array(
            'UserService' => 'Auth\Factory\UserServiceFactory',
        ),
        'invokables' => array(
            'AclService'  => 'Auth\Service\AclService',
        ),
    ),

    // ── Doctrine ORM configuration ──────────────────────────────────────────
    'doctrine' => array(
        'driver' => array(
            'auth_annotation_driver' => array(
                'class' => 'Doctrine\ORM\Mapping\Driver\AnnotationDriver',
                'paths' => array(
                    __DIR__ . '/../src/Auth/Entity',
                ),
            ),
            'orm_default' => array(
                'drivers' => array(
                    'Auth\Entity' => 'auth_annotation_driver',
                ),
            ),
        ),
    ),
    // ─────────────────────────────────────────────────────────────────────────

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

    'view_helpers' => array(
        'factories' => array(
            'isAllowed' => 'Auth\Factory\IsAllowedFactory',
        ),
    ),

    // View manager — tell ZF2 where to find our .phtml templates
    'view_manager' => array(
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
    ),
);
