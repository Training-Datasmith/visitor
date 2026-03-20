<?php

declare (strict_types=1);
namespace Shetabit\Visitor\Resolvers\Geo_Ip;

use Shetabit\Visitor\Contracts\Geo_Ip_Resolver;
use Stevebauman\Location\Facades\Location;
class Steve_Bauman_Resolver implements Geo_Ip_Resolver
{
    public function resolve(string $ip): ?array
    {
        $position = Location::get($ip);
        if (!$position) {
            return null;
        }
        return ['ip' => $position->ip, 'country_code' => $position->country_code, 'country_name' => $position->country_name, 'region_name' => $position->region_name, 'city_name' => $position->city_name, 'latitude' => $position->latitude, 'longitude' => $position->longitude];
    }
}