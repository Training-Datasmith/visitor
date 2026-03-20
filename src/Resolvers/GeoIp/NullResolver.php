<?php

declare (strict_types=1);
namespace Shetabit\Visitor\Resolvers\Geo_Ip;

use Shetabit\Visitor\Contracts\Geo_Ip_Resolver;
class Null_Resolver implements Geo_Ip_Resolver
{
    public function resolve(string $ip): ?array
    {
        return null;
    }
}