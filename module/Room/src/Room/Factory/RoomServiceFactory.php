<?php
namespace Room\Factory;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Room\Service\RoomService;

/**
 * RoomServiceFactory — creates RoomService with Doctrine's EntityManager injected.
 *
 * Lecture 21: Doctrine ORM in ZF2
 */
class RoomServiceFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $sm)
    {
        $em = $sm->get('Doctrine\ORM\EntityManager');

        return new RoomService($em);
    }
}
