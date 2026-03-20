<?php

declare (strict_types=1);
namespace Shetabit\Visitor\Provider;

use Illuminate\Support\Service_Provider;
use Shetabit\Agent\Agent;
class Agent_Service_Provider extends Service_Provider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('agent', fn($app) => new Agent($app['request']->server()));
        $this->app->alias('agent', Agent::class);
    }
    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['agent', Agent::class];
    }
}