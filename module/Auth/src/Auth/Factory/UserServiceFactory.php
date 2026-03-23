<?php
namespace Auth\Factory;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Auth\Service\UserService;

/**
 * UserServiceFactory — creates UserService with Doctrine's EntityManager.
 */
class UserServiceFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $sm)
    {
        $em = $sm->get('Doctrine\ORM\EntityManager');

        return new UserService($em);
    }
}
