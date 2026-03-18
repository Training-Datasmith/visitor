<?php

declare(strict_types=1);

namespace Shetabit\Visitor\Contracts;

interface GeoIpResolver
{
    /**
     * Resolve a given IP address into a GeoIP payload.
     *
     * @return array|null
     *
     * Example return:
     * [
     *   'ip'           => '192.168.1.1',
     *   'country_code' => 'FR',
     *   'country_name' => 'France',
     *   'region_name'  => 'Île-de-France',
     *   'city_name'    => 'Paris',
     *   'latitude'     => 48.8566,
     *   'longitude'    => 2.3522,
     * ]
     */
    public function resolve(string $ip): ?array;
}
