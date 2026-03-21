<?php
namespace Room\Factory;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Room\Controller\RoomController;

/**
 * RoomControllerFactory — creates RoomController with its dependencies injected.
 *
 * This is the Dependency Injection (DI) pattern:
 *   the object receives what it needs through the constructor,
 *   rather than reaching out and fetching it itself (ServiceLocator pattern).
 *
 * ZF2 calls createService() automatically when the ControllerManager
 * needs to build 'Room\Controller\Room'.
 */
class RoomControllerFactory implements FactoryInterface
{
    /**
     * @param  ServiceLocatorInterface $controllerManager  The ControllerManager (a child locator).
     * @return RoomController
     */
    public function createService(ServiceLocatorInterface $controllerManager)
    {
        $serviceManager = $controllerManager->getServiceLocator();

        $roomService    = $serviceManager->get('RoomService');
        $paymentService = $serviceManager->get('PaymentService');

        return new RoomController($roomService, $paymentService);
    }
}
