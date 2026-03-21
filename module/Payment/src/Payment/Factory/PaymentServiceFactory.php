<?php
namespace Payment\Factory;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Payment\Service\PaymentService;

class PaymentServiceFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $sm)
    {
        $config  = $sm->get('Config');
        $payment = $config['payment'] ?? [];

        return new PaymentService($payment);
    }
}
