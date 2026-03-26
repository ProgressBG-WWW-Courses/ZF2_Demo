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
        $roomService    = $sm->get('RoomService');

        return new PaymentController($paymentService, $roomService);
    }
}
