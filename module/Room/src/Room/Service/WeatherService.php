<?php
namespace Room\Service;

use Zend\Http\Client;

/**
 * WeatherService — fetches current weather from wttr.in (no API key required).
 *
 * Demonstrates consuming an external JSON API using Zend\Http\Client.
 * wttr.in is a public weather service that returns JSON via the ?format=j1 parameter.
 *
 * Added in Lecture 25 (API Development & Integration).
 */
class WeatherService
{
    /** @var string The city name to fetch weather for */
    private $city;

    /**
     * Constructor receives the city via Dependency Injection (from WeatherServiceFactory).
     *
     * @param string $city  e.g. 'Sofia'
     */
    public function __construct($city)
    {
        $this->city = $city;
    }

    /**
     * Calls wttr.in and returns the current weather for the configured city.
     *
     * Returns an associative array on success, or null if the request fails
     * (e.g. no internet, the weather service is down).
     * Returning null instead of throwing lets the controller display the page
     * normally — weather is a "nice to have", not a critical dependency.
     *
     * @return array|null  ['temperature' => '12', 'description' => 'Partly cloudy', 'city' => 'Sofia']
     */
    public function getCurrentWeather()
    {
        // Zend\Http\Client takes the base URL in the constructor.
        // Query parameters (?format=j1) are added separately with setParameterGet().
        $client = new Client('https://wttr.in/' . urlencode($this->city));
        $client->setParameterGet(['format' => 'j1']);

        try {
            $response = $client->send();

            // isSuccess() returns true for any 2xx HTTP status code
            if (!$response->isSuccess()) {
                return null;
            }

            // json_decode with true returns a PHP array (not an object)
            $data = json_decode($response->getBody(), true);

            if (!$data || !isset($data['current_condition'][0])) {
                return null;
            }

            $current = $data['current_condition'][0];

            return [
                'temperature' => $current['temp_C'],
                'description' => $current['weatherDesc'][0]['value'],
                'city'        => $this->city,
            ];
        } catch (\Exception $e) {
            // If the weather API is down, return null gracefully.
            // The controller checks for null and skips the weather widget.
            return null;
        }
    }
}
