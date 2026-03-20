<?php

declare (strict_types=1);
namespace Shetabit\Visitor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Shetabit\Visitor\Contracts\{Geo_Ip_Resolver, User_Agent_Parser};
use Shetabit\Visitor\Exceptions\Driver_Not_Found_Exception;
use Shetabit\Visitor\Models\Visit;
class Visitor implements User_Agent_Parser, Geo_Ip_Resolver
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
    protected $driver_instance;
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
    protected $resolver_instance;
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
    public function __construct(
        Request $request,
        /**
         * Configuration.
         */
        protected $config
    )
    {
        $this->request = $request;
        $this->except = $this->config['except'];
        $this->via($this->config['default'], $this->config['resolver']);
        $this->set_visitor($request->user());
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
        $this->validate_driver();
        $this->resolver = $resolver;
        $this->validate_resolver();
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
        return $this->request->full_url();
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
        return $this->request->get_method();
    }
    /**
     * Retrieve http headers.
     */
    public function http_headers(): array
    {
        return $this->request->headers->all();
    }
    /**
     * Retrieve agent.
     */
    public function user_agent(): string
    {
        return $this->request->user_agent() ?? '';
    }
    /**
     * Retrieve device's name.
     *
     *
     * @throws \Exception
     */
    public function device(): string
    {
        return $this->get_driver_instance()->device();
    }
    /**
     * Retrieve platform's name.
     *
     *
     * @throws \Exception
     */
    public function platform(): string
    {
        return $this->get_driver_instance()->platform();
    }
    /**
     * Retrieve browser's name.
     *
     *
     * @throws \Exception
     */
    public function browser(): string
    {
        return $this->get_driver_instance()->browser();
    }
    /**
     * Retrieve languages.
     *
     *
     * @throws \Exception
     */
    public function languages(): array
    {
        return $this->get_driver_instance()->languages();
    }
    /**
     *
     */
    public function resolve(string $ip): ?array
    {
        if (!($this->config['geoip'] ?? false)) {
            return null;
        }
        return $this->get_resolver_instance()->resolve($ip);
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
    public function set_visitor(?Model $user): static
    {
        $this->visitor = $user;
        return $this;
    }
    /**
     * Retrieve visitor (user)
     */
    public function get_visitor(): ?Model
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
        $data = $this->prepare_log();
        if (null !== $model && method_exists($model, 'visitLogs')) {
            return $model->visit_logs()->create($data);
        }
        return Visit::create($data);
    }
    /**
     * Retrieve online visitors.
     *
     * @param int $seconds
     */
    public function online_visitors(string $model, $seconds = 180)
    {
        return app($model)->online()->get();
    }
    /**
     * Determine if given visitor or current one is online.
     *
     * @param int $seconds
     * @return bool
     */
    public function is_online(?Model $visitor = null, $seconds = 180)
    {
        $time = now()->sub_seconds($seconds);
        $visitor ??= $this->get_visitor();
        if (!$visitor instanceof \Illuminate\Database\Eloquent\Model) {
            return false;
        }
        return Visit::where_has_morph('visitor', $visitor::class, function ($query) use ($visitor): void {
            $query->where('visitor_id', $visitor->id);
        })->where_date('created_at', '>=', $time)->count() > 0;
    }
    /**
     * Prepare log's data.
     *
     *
     * @throws \Exception
     */
    protected function prepare_log(): array
    {
        $log = ['method' => $this->method(), 'request' => $this->request(), 'url' => $this->url(), 'referer' => $this->referer(), 'languages' => $this->languages(), 'useragent' => $this->user_agent(), 'headers' => $this->http_headers(), 'device' => $this->device(), 'platform' => $this->platform(), 'browser' => $this->browser(), 'ip' => $this->ip(), 'visitor_id' => $this->get_visitor()?->id, 'visitor_type' => $this->get_visitor()?->get_morph_class()];
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
    protected function get_driver_instance()
    {
        if (!empty($this->driver_instance)) {
            return $this->driver_instance;
        }
        return $this->get_fresh_driver_instance();
    }
    /**
     * Get new driver instance
     *
     * @return Driver
     *
     * @throws \Exception
     */
    protected function get_fresh_driver_instance()
    {
        $this->validate_driver();
        $driver_class = $this->config['drivers'][$this->driver];
        return app($driver_class);
    }
    /**
     * Validate driver.
     *
     * @throws \Exception
     */
    protected function validate_driver()
    {
        if (empty($this->driver)) {
            throw new Driver_Not_Found_Exception('Driver not selected or default driver does not exist.');
        }
        $driver_class = $this->config['drivers'][$this->driver];
        if (empty($driver_class) || !class_exists($driver_class)) {
            throw new Driver_Not_Found_Exception('Driver not found in config file. Try updating the package.');
        }
        $reflect = new \ReflectionClass($driver_class);
        if (!$reflect->implements_interface(User_Agent_Parser::class)) {
            throw new \Exception("Driver must be an instance of Contracts\\Driver.");
        }
    }
    /**
     * Retrieve current resolver instance or generate new one.
     *
     * @return mixed|object
     *
     * @throws \Exception
     */
    protected function get_resolver_instance()
    {
        if (!empty($this->resolver_instance)) {
            return $this->resolver_instance;
        }
        return $this->get_fresh_resolver_instance();
    }
    /**
     * Get new resolver instance
     *
     * @return Resolver
     *
     * @throws \Exception
     */
    protected function get_fresh_resolver_instance()
    {
        $this->validate_resolver();
        $resolver_class = $this->config['resolvers'][$this->resolver];
        return app($resolver_class);
    }
    /**
     * Validate resolver.
     *
     * @throws \Exception
     */
    protected function validate_resolver()
    {
        if (empty($this->resolver)) {
            throw new Resolver_Not_Found_Exception('Resolver not selected or default resolver does not exist.');
        }
        $resolver_class = $this->config['resolvers'][$this->resolver];
        if (empty($resolver_class) || !class_exists($resolver_class)) {
            throw new Resolver_Not_Found_Exception('Resolver not found in config file. Try updating the package.');
        }
        $reflect = new \ReflectionClass($resolver_class);
        if (!$reflect->implements_interface(Geo_Ip_Resolver::class)) {
            throw new \Exception("Resolver must be an instance of Contracts\\Resolver.");
        }
    }
}