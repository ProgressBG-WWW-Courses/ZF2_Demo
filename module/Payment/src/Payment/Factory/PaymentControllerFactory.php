<?php
namespace Payment\Factory;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Payment\Controller\PaymentController;

class PaymentControllerFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $controllerManager)
    {
        $sm             = $controllerManager->getServiceLocator();
        $paymentService = $sm->get('PaymentService');

        return new PaymentController($paymentService);
    }
}
