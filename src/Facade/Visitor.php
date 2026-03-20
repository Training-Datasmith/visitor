<?php

declare (strict_types=1);
namespace Shetabit\Visitor\Facade;

use Illuminate\Support\Facades\Facade;
/**
 * Class viewer
 *
 * @package Shetabit\Visitor\Facade
 */
class Visitor extends Facade
{
    /**
     * Get the registered name of the component.
     */
    public static function get_facade_accessor(): string
    {
        return 'shetabit-visitor';
    }
}