<?php
// module/Auth/src/Auth/Factory/AuthControllerFactory.php
namespace Auth\Factory;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Auth\Controller\AuthController;

/**
 * AuthControllerFactory — creates AuthController with UserService injected.
 *
 * Same Dependency Injection pattern as RoomControllerFactory.
 */
class AuthControllerFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $controllerManager)
    {
        $serviceManager = $controllerManager->getServiceLocator();

        /** @var \Auth\Service\UserService $userService */
        $userService = $serviceManager->get('UserService');

        return new AuthController($userService);
    }
}
