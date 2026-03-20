<?php

declare(strict_types=1);

namespace Shetabit\Visitor\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return ['Shetabit\Visitor\Provider\VisitorServiceProvider'];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Visitor' => 'Shetabit\Visitor\Facade\Visitor',
        ];
    }
}
