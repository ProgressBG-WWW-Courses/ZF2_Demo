<?php
namespace Room\Factory;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Room\Service\RoomService;

/**
 * RoomServiceFactory — creates RoomService and injects its dependencies.
 *
 * In a real application with Doctrine configured, the factory would get the
 * EntityManager from the ServiceManager and pass it to the service:
 *
 *   $em = $sm->get('Doctrine\ORM\EntityManager');
 *   return new RoomService($em);
 *
 * This demo uses hardcoded room data, so no EntityManager is needed.
 * The factory still exists to demonstrate the wiring pattern from Lecture 21.
 *
 * Lecture 21: Doctrine ORM in ZF2
 */
class RoomServiceFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $sm)
    {
        // With Doctrine installed and configured, you would uncomment this:
        // $em = $sm->get('Doctrine\ORM\EntityManager');
        // return new RoomService($em);

        // Demo: service works with hardcoded data, no EntityManager needed.
        return new RoomService();
    }
}
