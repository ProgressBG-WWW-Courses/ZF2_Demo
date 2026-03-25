<?php
namespace Room\Factory;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Room\Controller\RoomApiController;

class RoomApiControllerFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $controllerManager)
    {
        $serviceManager = $controllerManager->getServiceLocator();
        $roomService    = $serviceManager->get('RoomService');

        return new RoomApiController($roomService);
    }
}
