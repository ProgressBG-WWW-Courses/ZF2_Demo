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
        // The ControllerManager is a child ServiceLocator.
        // getServiceLocator() returns the parent (application-level) ServiceManager,
        // where RoomService is registered.
        $serviceManager = $controllerManager->getServiceLocator();

        /** @var \Room\Service\RoomService $roomService */
        $roomService = $serviceManager->get('RoomService');

        // Inject the service through the constructor — this is Dependency Injection.
        return new RoomController($roomService);
    }
}
