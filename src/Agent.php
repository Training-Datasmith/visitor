<?php

declare (strict_types=1);
namespace Shetabit\Visitor;

use BadMethodCallException;
use Closure;
use Detection\Exception\Mobile_Detect_Exception;
use Detection\Mobile_Detect;
use Jaybizzle\Crawler_Detect\Crawler_Detect;
use Random\Random_Exception;
class Agent extends Mobile_Detect
{
    /**
     * List of desktop devices.
     *
     * @var array<string, string>
     */
    protected static array $desktop_devices = ['Macintosh' => 'Macintosh'];
    /**
     * List of additional operating systems.
     *
     * @var array<string, string>
     */
    protected static array $additional_operating_systems = ['Windows' => 'Windows', 'Windows NT' => 'Windows NT', 'OS X' => 'Mac OS X', 'Debian' => 'Debian', 'Ubuntu' => 'Ubuntu', 'Macintosh' => 'PPC', 'OpenBSD' => 'OpenBSD', 'Linux' => 'Linux', 'ChromeOS' => 'CrOS'];
    /**
     * List of additional browsers.
     *
     * @var array<string, string>
     */
    protected static array $additional_browsers = ['Opera Mini' => 'Opera Mini', 'Opera' => 'Opera|OPR', 'Edge' => 'Edge|Edg', 'Coc Coc' => 'coc_coc_browser', 'UCBrowser' => 'UCBrowser', 'Vivaldi' => 'Vivaldi', 'Chrome' => 'Chrome', 'Firefox' => 'Firefox', 'Safari' => 'Safari', 'IE' => 'MSIE|IEMobile|MSIEMobile|Trident/[.0-9]+', 'Netscape' => 'Netscape', 'Mozilla' => 'Mozilla', 'WeChat' => 'MicroMessenger'];
    protected static Crawler_Detect $crawler_detect;
    /**
     * Key value store for resolved strings.
     *
     * @var array<string, mixed>
     */
    protected array $store = [];
    public function get_rules(): array
    {
        static $rules;
        if (!$rules) {
            $rules = array_merge(
                static::$browsers,
                static::$operating_systems,
                static::$phone_devices,
                static::$tablet_devices,
                static::$desktop_devices,
                // NEW
                static::$additional_operating_systems,
                // NEW
                static::$additional_browsers
            );
        }
        return $rules;
    }
    /**
     * Get accept languages.
     * @param string|null $acceptLanguage
     */
    public function languages(string $accept_language = null): array
    {
        if ($accept_language === null) {
            $accept_language = $this->get_http_header('HTTP_ACCEPT_LANGUAGE');
        }
        if (!$accept_language) {
            return [];
        }
        $languages = [];
        // Parse accept language string.
        foreach (explode(',', $accept_language) as $piece) {
            $parts = explode(';', $piece);
            $language = strtolower($parts[0]);
            $priority = empty($parts[1]) ? 1.0 : (float) str_replace('q=', '', $parts[1]);
            $languages[$language] = $priority;
        }
        // Sort languages by priority.
        arsort($languages);
        return array_keys($languages);
    }
    /**
     * Get the browser name.
     */
    public function browser(): bool|string
    {
        return $this->retrieve_using_cache_or_resolve('visitor.browser', fn() => $this->find_detection_rules_against_user_agent($this->merge_rules(static::$additional_browsers, Mobile_Detect::get_browsers())));
    }
    /**
     * Retrieve from the given key from the cache or resolve the value.
     *
     * @param \Closure():mixed $callback
     */
    protected function retrieve_using_cache_or_resolve(string $key, Closure $callback): mixed
    {
        $cache_key = $this->create_cache_key($key);
        if (!is_null($cache_item = $this->store[$cache_key] ?? null)) {
            return $cache_item;
        }
        return tap($callback(), function ($result) use ($cache_key): void {
            $this->store[$cache_key] = $result;
        });
    }
    /**
     * @throws RandomException
     */
    protected function create_cache_key(string $key): string
    {
        $user_agent_key = $this->has_user_agent() ? $this->user_agent : '';
        $random_bytes = random_bytes(16);
        $random_hash = bin2hex($random_bytes);
        return base64_encode("{$key}:{$user_agent_key}:{$random_hash}");
    }
    /**
     * Match a detection rule and return the matched key.
     */
    protected function find_detection_rules_against_user_agent(array $rules): ?string
    {
        $user_agent = $this->get_user_agent();
        foreach ($rules as $key => $regex) {
            if (empty($regex)) {
                continue;
            }
            // regex is an array of "strings"
            if (is_array($regex)) {
                foreach ($regex as $regex_string) {
                    if ($this->match($regex_string, $user_agent)) {
                        return $key ?: reset($this->matches_array);
                    }
                }
            } elseif ($this->match($regex, $user_agent)) {
                return $key ?: reset($this->matches_array);
            }
        }
        return false;
    }
    /**
     * Merge multiple rules into one array.
     *
     * @param array $all
     * @return array<string, string>
     */
    protected function merge_rules(...$all): array
    {
        $merged = [];
        foreach ($all as $rules) {
            foreach ($rules as $key => $value) {
                if (empty($merged[$key])) {
                    $merged[$key] = $value;
                } elseif (is_array($merged[$key])) {
                    $merged[$key][] = $value;
                } else {
                    $merged[$key] .= '|' . $value;
                }
            }
        }
        return $merged;
    }
    /**
     * Get the platform name.
     */
    public function platform(): bool|string
    {
        return $this->retrieve_using_cache_or_resolve('visitor.platform', fn() => $this->find_detection_rules_against_user_agent($this->merge_rules(static::$additional_operating_systems, Mobile_Detect::get_operating_systems())));
    }
    /**
     * Get the device name.
     */
    public function device(): bool|string
    {
        return $this->find_detection_rules_against_user_agent($this->merge_rules(static::get_desktop_devices(), static::get_phone_devices(), static::get_tablet_devices()));
    }
    /**
     * Retrieve the list of known Desktop devices.
     *
     * @return array List of Desktop devices.
     */
    public static function get_desktop_devices(): array
    {
        return static::$desktop_devices;
    }
    /**
     * Get the robot name.
     */
    public function robot(): bool|string
    {
        $user_agent = $this->get_user_agent();
        if ($this->get_crawler_detect()->is_crawler($user_agent ?: $this->user_agent)) {
            return ucfirst($this->get_crawler_detect()->get_matches());
        }
        return false;
    }
    public function get_crawler_detect(): Crawler_Detect
    {
        if (static::$crawler_detect === null) {
            static::$crawler_detect = new Crawler_Detect();
        }
        return static::$crawler_detect;
    }
    /**
     * Get the device type
     * @throws MobileDetectException
     */
    public function device_type(): string
    {
        if ($this->is_desktop()) {
            return 'desktop';
        }
        if ($this->is_phone()) {
            return 'phone';
        }
        if ($this->is_tablet()) {
            return 'tablet';
        }
        if ($this->is_robot()) {
            return 'robot';
        }
        return 'other';
    }
    /**
     * Check if the device is a desktop computer.
     * @throws MobileDetectException
     */
    public function is_desktop(): bool
    {
        $user_agent = $this->get_user_agent();
        return $this->retrieve_using_cache_or_resolve('visitor.desktop', function () use ($user_agent): bool {
            // Check specifically for cloudfront headers if the useragent === 'Amazon CloudFront'
            if ($user_agent === static::$cloud_front_ua && $this->get_http_header('HTTP_CLOUDFRONT_IS_DESKTOP_VIEWER') === 'true') {
                return true;
            }
            return !$this->is_mobile() && !$this->is_tablet() && !$this->is_robot();
        });
    }
    /**
     * Check if device is a robot.
     */
    public function is_robot(): bool
    {
        $user_agent = $this->get_user_agent();
        return $this->get_crawler_detect()->is_crawler($user_agent ?: $this->user_agent);
    }
    /**
     * Check if the device is a mobile phone.
     * @throws MobileDetectException
     */
    public function is_phone(): bool
    {
        return $this->is_mobile() && !$this->is_tablet();
    }
    /**
     * @inheritdoc
     */
    public function __call($name, $arguments)
    {
        // Make sure the name starts with 'is', otherwise
        if (!str_starts_with($name, 'is')) {
            throw new BadMethodCallException("No such method exists: {$name}");
        }
        $key = substr($name, 2);
        return $this->match_user_agent_with_rule($key);
    }
}