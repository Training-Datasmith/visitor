<?php

declare(strict_types=1);

namespace Shetabit\Visitor\Resolvers\GeoIp;

use Shetabit\Visitor\Contracts\GeoIpResolver;

class NullResolver implements GeoIpResolver
{
    public function resolve(string $ip): ?array
    {
        return null;
    }
}
