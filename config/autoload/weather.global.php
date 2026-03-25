<?php
/**
 * Weather configuration — wttr.in API settings.
 *
 * wttr.in is a free weather service that requires no API key.
 * The city can be overridden via the WEATHER_CITY environment variable in .env.
 *
 * Consumed by WeatherServiceFactory to build WeatherService.
 * Added in Lecture 25 (API Development & Integration).
 */
return [
    'weather' => [
        'city' => getenv('WEATHER_CITY') ?: 'Sofia',
    ],
];
