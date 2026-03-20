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
     * Retrieves all input data from the current HTTP request.
     *
     * @return array<string, mixed>
     */
    public function request(): array
    {
        return $this->request->all();
    }
    /**
     * Retrieves the visitor's IP address from the current request.
     *
     * @return string|null The IP address, or null if it cannot be determined
     */
    public function ip(): ?string
    {
        return $this->request->ip();
    }
    /**
     * Retrieves the full URL of the current request.
     *
     * @return string The full request URL including query string
     */
    public function url(): string
    {
        return $this->request->full_url();
    }
    /**
     * Retrieves the HTTP Referer header value from the current request.
     *
     * @return string|null The referring URL, or null if no referer header was sent
     */
    public function referer(): ?string
    {
        return $this->request->header('referer');
    }
    /**
     * Retrieves the HTTP method (verb) of the current request.
     *
     * @return string Uppercase HTTP method (e.g. 'GET', 'POST')
     */
    public function method(): string
    {
        return $this->request->get_method();
    }
    /**
     * Retrieves all HTTP headers from the current request.
     *
     * @return array<string, string[]> Map of header name to array of values
     */
    public function http_headers(): array
    {
        return $this->request->headers->all();
    }
    /**
     * Retrieves the raw User-Agent string from the current request.
     *
     * @return string The User-Agent header value, or empty string if not present
     */
    public function user_agent(): string
    {
        return $this->request->user_agent() ?? '';
    }
    /**
     * Retrieves the detected device type (e.g. 'desktop', 'mobile', 'tablet') from the User-Agent.
     *
     * @return string Device type name as reported by the configured UA parser driver
     *
     * @throws \Exception If the configured UA parser driver cannot be instantiated
     */
    public function device(): string
    {
        return $this->get_driver_instance()->device();
    }
    /**
     * Retrieves the detected operating system/platform name from the User-Agent.
     *
     * @return string Platform name (e.g. 'Windows', 'iOS', 'Linux')
     *
     * @throws \Exception If the configured UA parser driver cannot be instantiated
     */
    public function platform(): string
    {
        return $this->get_driver_instance()->platform();
    }
    /**
     * Retrieves the detected browser name from the User-Agent.
     *
     * @return string Browser name (e.g. 'Chrome', 'Firefox', 'Safari')
     *
     * @throws \Exception If the configured UA parser driver cannot be instantiated
     */
    public function browser(): string
    {
        return $this->get_driver_instance()->browser();
    }
    /**
     * Retrieves the list of languages accepted by the visitor's browser.
     *
     * @return string[] List of language tags (e.g. ['en-US', 'fr'])
     *
     * @throws \Exception If the configured UA parser driver cannot be instantiated
     */
    public function languages(): array
    {
        return $this->get_driver_instance()->languages();
    }
    /**
     * Resolves geographic location data for the given IP address using the configured GeoIP resolver.
     *
     * @param string $ip IPv4 or IPv6 address to resolve
     *
     * @return array<string, mixed>|null Location data map, or null if GeoIP is disabled or resolution fails
     */
    public function resolve(string $ip): ?array
    {
        if (!($this->config['geoip'] ?? false)) {
            return null;
        }
        return $this->get_resolver_instance()->resolve($ip);
    }

    /**
     * Resolves geographic location data for the current visitor's IP address.
     *
     * @return array<string, mixed>|null Location data map, or null if IP is unavailable or GeoIP is disabled
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
     * Records a visit log entry for the current request, optionally associated with a visited model.
     * Skips logging if the request path matches an excluded pattern in the config.
     *
     * @param Model|null $model Optional Eloquent model being visited (e.g. a Product or Page)
     *
     * @return Visit|null The created visit record, or null if logging was skipped
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
     * Retrieves all visitors currently online for a given model class.
     *
     * @param string $model   Fully-qualified Eloquent model class name (must use the online() scope)
     * @param int    $seconds Number of seconds within which a visit is considered "online" (default 180)
     *
     * @return \Illuminate\Database\Eloquent\Collection Collection of online visitor models
     */
    public function online_visitors(string $model, $seconds = 180)
    {
        return app($model)->online()->get();
    }
    /**
     * Determines whether the given visitor (or the current authenticated visitor) is online.
     *
     * @param Model|null $visitor The visitor model to check; defaults to the current visitor if null
     * @param int        $seconds Number of seconds within which a visit is considered "online" (default 180)
     *
     * @return bool True if the visitor has a visit record within the given window, false otherwise
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