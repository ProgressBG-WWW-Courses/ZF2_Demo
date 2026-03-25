<?php
namespace Room\Factory;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Room\Service\WeatherService;

/**
 * WeatherServiceFactory — builds WeatherService with its configuration.
 *
 * Reads the city from the global Config service (set in config/autoload/weather.global.php)
 * and passes it to WeatherService via the constructor.
 *
 * This is the same Dependency Injection pattern used by RoomServiceFactory
 * and PaymentServiceFactory — the factory wires up configuration so the
 * service itself stays clean and testable.
 */
class WeatherServiceFactory implements FactoryInterface
{
    /**
     * @param  ServiceLocatorInterface $sm
     * @return WeatherService
     */
    public function createService(ServiceLocatorInterface $sm)
    {
        $config = $sm->get('Config');
        $city   = isset($config['weather']['city']) ? $config['weather']['city'] : 'Sofia';

        return new WeatherService($city);
    }
}
