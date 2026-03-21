<?php
return [

    'service_manager' => [
        'factories' => [
            'PaymentService' => 'Payment\Factory\PaymentServiceFactory',
        ],
    ],

    // ── Doctrine ORM configuration ────────────────────────────────────────
    // Uncomment this block when DoctrineORMModule is installed.
    // Until then, PaymentService uses PDO directly with the same schema.
    //
    // 'doctrine' => [
    //     'driver' => [
    //         'payment_annotation_driver' => [
    //             'class' => 'Doctrine\ORM\Mapping\Driver\AnnotationDriver',
    //             'paths' => [
    //                 __DIR__ . '/../src/Payment/Entity',
    //             ],
    //         ],
    //         'orm_default' => [
    //             'drivers' => [
    //                 'Payment\Entity' => 'payment_annotation_driver',
    //             ],
    //         ],
    //     ],
    // ],
    // ──────────────────────────────────────────────────────────────────────

    'controllers' => [
        'factories' => [
            'Payment\Controller\Payment' => 'Payment\Factory\PaymentControllerFactory',
        ],
    ],

    'router' => [
        'routes' => [
            'payment' => [
                'type'    => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route'    => '/payment',
                    'defaults' => [
                        'controller' => 'Payment\Controller\Payment',
                    ],
                ],
                'may_terminate' => false,
                'child_routes'  => [

                    // POST /payment/create — create Revolut order
                    'create' => [
                        'type'    => 'Zend\Mvc\Router\Http\Literal',
                        'options' => [
                            'route'    => '/create',
                            'defaults' => ['action' => 'create'],
                        ],
                    ],

                    // GET /payment/success — post-payment redirect
                    'success' => [
                        'type'    => 'Zend\Mvc\Router\Http\Literal',
                        'options' => [
                            'route'    => '/success',
                            'defaults' => ['action' => 'success'],
                        ],
                    ],

                    // GET /payment/cancel — user cancelled checkout
                    'cancel' => [
                        'type'    => 'Zend\Mvc\Router\Http\Literal',
                        'options' => [
                            'route'    => '/cancel',
                            'defaults' => ['action' => 'cancel'],
                        ],
                    ],

                    // POST /payment/webhook — Revolut webhook endpoint
                    'webhook' => [
                        'type'    => 'Zend\Mvc\Router\Http\Literal',
                        'options' => [
                            'route'    => '/webhook',
                            'defaults' => ['action' => 'webhook'],
                        ],
                    ],

                    // GET /payment/status/:order_id — JSON polling endpoint
                    'status' => [
                        'type'    => 'Zend\Mvc\Router\Http\Segment',
                        'options' => [
                            'route'       => '/status/:order_id',
                            'constraints' => [
                                'order_id' => '[a-zA-Z0-9_-]+',
                            ],
                            'defaults' => ['action' => 'status'],
                        ],
                    ],
                ],
            ],
        ],
    ],

    'view_manager' => [
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
];
