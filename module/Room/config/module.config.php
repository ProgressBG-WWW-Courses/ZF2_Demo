<?php
return array(
    // Register RoomService via a factory (Lecture 21).
    // 'factories' lets us inject dependencies — like Doctrine's EntityManager —
    // through the constructor instead of reaching for them inside the class.
    // RoomServiceFactory::createService() builds RoomService and wires it up.
    'service_manager' => array(
        'factories' => array(
            'RoomService' => 'Room\Factory\RoomServiceFactory',
        ),
    ),

    // ── Doctrine ORM configuration (Lecture 21) ──────────────────────────────
    // Tells Doctrine where to find entity classes and how to read their
    // annotations for mapping to database tables.
    'doctrine' => array(
        'driver' => array(
            'room_annotation_driver' => array(
                'class' => 'Doctrine\ORM\Mapping\Driver\AnnotationDriver',
                'paths' => array(
                    __DIR__ . '/../src/Room/Entity',
                ),
            ),
            'orm_default' => array(
                'drivers' => array(
                    'Room\Entity' => 'room_annotation_driver',
                ),
            ),
        ),
    ),
    // ─────────────────────────────────────────────────────────────────────────

    // Register our controller using a factory so that RoomService can be injected.
    // The factory (RoomControllerFactory) fetches RoomService and passes it to
    // RoomController's constructor — this is the Dependency Injection pattern.
    'controllers' => array(
        'factories' => array(
            'Room\Controller\Room'    => 'Room\Factory\RoomControllerFactory',
            'Room\Controller\RoomApi' => 'Room\Factory\RoomApiControllerFactory',
        ),
    ),

    // Route configuration — this is where the magic happens!
    'router' => array(
        'routes' => array(

            // ── Standalone Literal route ──────────────────────────
            // This route is NOT a child — it lives on its own.
            // Good for pages that don't belong to a group.
            'room-about' => array(
                'type'    => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/room/about',
                    'defaults' => array(
                        'controller' => 'Room\Controller\Room',
                        'action'     => 'about',
                    ),
                ),
            ),

            // ── Parent route with children ───────────────────────
            // The parent route matches /room exactly.            
            // Child routes EXTEND the parent URL.
            'room' => array(
                'type'    => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/room',
                    'defaults' => array(
                        'controller' => 'Room\Controller\Room',
                        'action'     => 'index',
                    ),
                ),
                'may_terminate' => true,

                'child_routes' => array(

                    // Child Segment route: /room/detail/:id
                    // The :id part is a parameter — it captures a value from the URL.
                    // constraints limit :id to digits only (0-9).
                    // Try /room/detail/abc — ZF2 will show 404 because "abc" doesn't match [0-9]+
                    'detail' => array(
                        'type'    => 'Zend\Mvc\Router\Http\Segment',
                        'options' => array(
                            'route'       => '/detail/:id',
                            'constraints' => array(
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'action' => 'detail',
                            ),
                        ),
                    ),

                    // Child Literal route: /room/search
                    // Query parameters (?type=Suite&min_price=100) are NOT part of the route.
                    // We read them in the controller with params()->fromQuery().
                    'search' => array(
                        'type'    => 'Zend\Mvc\Router\Http\Literal',
                        'options' => array(
                            'route'    => '/search',
                            'defaults' => array(
                                'action' => 'search',
                            ),
                        ),
                    ),

                    // Child Literal route: /room/create
                    // Handles both GET (show form) and POST (process submission).
                    // The form includes a CSRF token — see RoomForm.php.
                    'create' => array(
                        'type'    => 'Zend\Mvc\Router\Http\Literal',
                        'options' => array(
                            'route'    => '/create',
                            'defaults' => array(
                                'action' => 'create',
                            ),
                        ),
                    ),

                ),
            ),

            // ── API routes ───────────────────────────────────────
            // GET /api/rooms        → indexAction (list all rooms)
            // GET /api/rooms/:id    → getAction   (single room)
            'api' => array(
                'type'          => 'Zend\Mvc\Router\Http\Literal',
                'options'       => array('route' => '/api'),
                'may_terminate' => false,
                'child_routes'  => array(
                    'rooms' => array(
                        'type'          => 'Zend\Mvc\Router\Http\Literal',
                        'options'       => array(
                            'route'    => '/rooms',
                            'defaults' => array(
                                'controller' => 'Room\Controller\RoomApi',
                                'action'     => 'index',
                            ),
                        ),
                        'may_terminate' => true,
                        'child_routes'  => array(
                            'get' => array(
                                'type'    => 'Zend\Mvc\Router\Http\Segment',
                                'options' => array(
                                    'route'       => '/:id',
                                    'constraints' => array('id' => '[0-9]+'),
                                    'defaults'    => array('action' => 'get'),
                                ),
                            ),
                        ),
                    ),
                ),
            ),

        ),
    ),

    // View manager — tell ZF2 where to find our .phtml templates
    // We use template_path_stack here (the Application module uses template_map).
    // Both approaches work — path_stack is simpler for modules with many templates.
    'view_manager' => array(
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
        'strategies' => [
            'ViewJsonStrategy',  // Enables JSON responses
        ],
    ),
);
