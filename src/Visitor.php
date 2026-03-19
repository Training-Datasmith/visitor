<?php

declare(strict_types=1);

namespace Shetabit\Visitor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Shetabit\Visitor\Contracts\{GeoIpResolver, UserAgentParser};
use Shetabit\Visitor\Exceptions\DriverNotFoundException;
use Shetabit\Visitor\Models\Visit;

class Visitor implements UserAgentParser, GeoIpResolver
{
    /**
     * except.
     *
     * @var array
     */
    protected $except;

    /**
     * Driver name.
     *
     * @var string
     */
    protected $driver;

    /**
     * Driver instance.
     *
     * @var object
     */
    protected $driverInstance;

    /**
     * Resolver name.
     *
     * @var string
     */
    protected $resolver;

    /**
     * Resolver instance.
     *
     * @var object
     */
    protected $resolverInstance;

    /**
     * Request instance.
     *
     * @var Request
     */
    protected $request;

    /**
     * Visitor (user) instance.
     *
     * @var Model|null
     */
    protected $visitor;

    /**
     * Visitor constructor.
     *
     * @param $config
     *
     * @throws \Exception
     * @param mixed[] $config
     */
    public function __construct(Request $request, /**
     * Configuration.
     */
        protected $config)
    {
        $this->request = $request;
        $this->except = $this->config['except'];
        $this->via($this->config['default'], $this->config['resolver']);
        $this->setVisitor($request->user());
    }

    /**
     * Change the driver and the resolver on the fly.
     *
     * @param $driver
     * @param $resolver
     *
     * @return $this
     *
     * @throws \Exception
     */
    public function via($driver, $resolver): static
    {
        $this->driver = $driver;
        $this->validateDriver();

        $this->resolver = $resolver;
        $this->validateResolver();

        return $this;
    }

    /**
     * Retrieve request's data
     */
    public function request(): array
    {
        return $this->request->all();
    }

    /**
     * Retrieve user's ip.
     */
    public function ip(): ?string
    {
        return $this->request->ip();
    }

    /**
     * Retrieve request's url
     */
    public function url(): string
    {
        return $this->request->fullUrl();
    }

    /**
     * Retrieve request's referer
     */
    public function referer(): ?string
    {
        return $this->request->header('referer');
    }

    /**
     * Retrieve request's method.
     */
    public function method(): string
    {
        return $this->request->getMethod();
    }

    /**
     * Retrieve http headers.
     */
    public function httpHeaders(): array
    {
        return $this->request->headers->all();
    }

    /**
     * Retrieve agent.
     */
    public function userAgent(): string
    {
        return $this->request->userAgent() ?? '';
    }

    /**
     * Retrieve device's name.
     *
     *
     * @throws \Exception
     */
    public function device(): string
    {
        return $this->getDriverInstance()->device();
    }

    /**
     * Retrieve platform's name.
     *
     *
     * @throws \Exception
     */
    public function platform(): string
    {
        return $this->getDriverInstance()->platform();
    }

    /**
     * Retrieve browser's name.
     *
     *
     * @throws \Exception
     */
    public function browser(): string
    {
        return $this->getDriverInstance()->browser();
    }

    /**
     * Retrieve languages.
     *
     *
     * @throws \Exception
     */
    public function languages(): array
    {
        return $this->getDriverInstance()->languages();
    }

    /**
    *
    */
    public function resolve(string $ip): ?array
    {
        if (!($this->config['geoip'] ?? false)) {
            return null;
        }
        return $this->getResolverInstance()->resolve($ip);
    }

    /**
    *
    */
    public function geolocation(): ?array
    {
        $ip = $this->ip();
        return $ip ? $this->resolve($ip) : null;
    }

    /**
     * Set visitor (user)
     *
     *
     * @return $this
     */
    public function setVisitor(?Model $user): static
    {
        $this->visitor = $user;

        return $this;
    }

    /**
     * Retrieve visitor (user)
     */
    public function getVisitor(): ?Model
    {
        return $this->visitor;
    }

    /**
     * Create a visit log.
     */
    public function visit(Model $model = null)
    {
        foreach ($this->except as $path) {
            if ($this->request->is($path)) {
                return;
            }
        }

        $data = $this->prepareLog();

        if (null !== $model && method_exists($model, 'visitLogs')) {
            return $model->visitLogs()->create($data);
        }

        return Visit::create($data);
    }

    /**
     * Retrieve online visitors.
     *
     * @param int $seconds
     */
    public function onlineVisitors(string $model, $seconds = 180)
    {
        return app($model)->online()->get();
    }

    /**
     * Determine if given visitor or current one is online.
     *
     * @param int $seconds
     * @return bool
     */
    public function isOnline(?Model $visitor = null, $seconds = 180)
    {
        $time = now()->subSeconds($seconds);

        $visitor ??= $this->getVisitor();

        if (!$visitor instanceof \Illuminate\Database\Eloquent\Model) {
            return false;
        }

        return Visit::whereHasMorph('visitor', $visitor::class, function ($query) use ($visitor): void {
            $query->where('visitor_id', $visitor->id);
        })->whereDate('created_at', '>=', $time)->count() > 0;
    }

    /**
     * Prepare log's data.
     *
     *
     * @throws \Exception
     */
    protected function prepareLog(): array
    {
        $log =  [
            'method' => $this->method(),
            'request' => $this->request(),
            'url' => $this->url(),
            'referer' => $this->referer(),
            'languages' => $this->languages(),
            'useragent' => $this->userAgent(),
            'headers' => $this->httpHeaders(),
            'device' => $this->device(),
            'platform' => $this->platform(),
            'browser' => $this->browser(),
            'ip' => $this->ip(),
            'visitor_id' => $this->getVisitor()?->id,
            'visitor_type' => $this->getVisitor()?->getMorphClass(),
        ];

        if (!empty($this->config['geoip'])) {
            $log['geo_raw'] = $this->geolocation();
        }

        return $log;
    }

    /**
     * Retrieve current driver instance or generate new one.
     *
     * @return mixed|object
     *
     * @throws \Exception
     */
    protected function getDriverInstance()
    {
        if (!empty($this->driverInstance)) {
            return $this->driverInstance;
        }

        return $this->getFreshDriverInstance();
    }

    /**
     * Get new driver instance
     *
     * @return Driver
     *
     * @throws \Exception
     */
    protected function getFreshDriverInstance()
    {
        $this->validateDriver();

        $driverClass = $this->config['drivers'][$this->driver];

        return app($driverClass);
    }

    /**
     * Validate driver.
     *
     * @throws \Exception
     */
    protected function validateDriver()
    {
        if (empty($this->driver)) {
            throw new DriverNotFoundException('Driver not selected or default driver does not exist.');
        }

        $driverClass = $this->config['drivers'][$this->driver];

        if (empty($driverClass) || !class_exists($driverClass)) {
            throw new DriverNotFoundException('Driver not found in config file. Try updating the package.');
        }

        $reflect = new \ReflectionClass($driverClass);

        if (!$reflect->implementsInterface(UserAgentParser::class)) {
            throw new \Exception("Driver must be an instance of Contracts\Driver.");
        }
    }

    /**
     * Retrieve current resolver instance or generate new one.
     *
     * @return mixed|object
     *
     * @throws \Exception
     */
    protected function getResolverInstance()
    {
        if (!empty($this->resolverInstance)) {
            return $this->resolverInstance;
        }

        return $this->getFreshResolverInstance();
    }

    /**
     * Get new resolver instance
     *
     * @return Resolver
     *
     * @throws \Exception
     */
    protected function getFreshResolverInstance()
    {
        $this->validateResolver();

        $resolverClass = $this->config['resolvers'][$this->resolver];

        return app($resolverClass);
    }

    /**
     * Validate resolver.
     *
     * @throws \Exception
     */
    protected function validateResolver()
    {
        if (empty($this->resolver)) {
            throw new ResolverNotFoundException('Resolver not selected or default resolver does not exist.');
        }

        $resolverClass = $this->config['resolvers'][$this->resolver];

        if (empty($resolverClass) || !class_exists($resolverClass)) {
            throw new ResolverNotFoundException('Resolver not found in config file. Try updating the package.');
        }

        $reflect = new \ReflectionClass($resolverClass);

        if (!$reflect->implementsInterface(GeoIpResolver::class)) {
            throw new \Exception("Resolver must be an instance of Contracts\Resolver.");
        }
    }
}
