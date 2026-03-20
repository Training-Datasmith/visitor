<?php

declare (strict_types=1);
namespace Shetabit\Agent\Facades;

use Illuminate\Support\Facades\Facade;
class Agent extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function get_facade_accessor(): string
    {
        return 'agent';
    }
}