<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */

namespace Hail\Session;

use Hail\Factory\{
    Cache,
    Redis,
    Database
};
use Hail\Http\Request;
use Hail\Http\Response;
use Hail\Util\ArrayTrait;

/**
 *
 * A central control point for new session segments, PHP session management
 * values, and CSRF token checking.
 *
 * @package Aura.Session
 *
 */
class Session
{
    use ArrayTrait;

    /**
     *
     * Session key for the "next" flash values.
     *
     * @const string
     *
     */
    const FLASH_NEXT = 'Hail\Session\Flash\Next';

    /**
     *
     * Session key for the "current" flash values.
     *
     * @const string
     *
     */
    const FLASH_NOW = 'Hail\Session\Flash\Now';

    const CSRF_TOKEN = CsrfToken::class;

    /**
     *
     * The CSRF token for this session.
     *
     * @var CsrfToken
     *
     */
    protected $csrfToken;

    /**
     *
     * Session cookie parameters.
     *
     * @var array
     *
     */
    protected $cookieParams = [];

    /**
     *
     * Have the flash values been moved forward?
     *
     * @var bool
     *
     */
    protected $flashMoved = false;

    /**
     * @var \SessionHandlerInterface
     */
    private $handler;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Response
     */
    private $response;

    /**
     *
     * Constructor
     *
     * @param array    $cookieParams
     * @param Request  $request
     * @param Response $response
     */
    public function __construct(array $cookieParams = [], Request $request = null, Response $response = null)
    {
        $this->request = $request;
        $this->response = $response;

        $this->cookieParams = session_get_cookie_params();

        foreach ($this->cookieParams as $k => &$v) {
            if (isset($params[$k])) {
                $v = $params[$k];
            }
        }
        unset($v);
    }

    public function setHandler(array $config)
    {
        $connect = $config['connect'] ?? [];

        switch (strtolower($config['handler'])) {
            case 'redis':
                $class = Handler\Redis::class;
                $conn = Redis::client($connect);
                break;

            case 'simple':
            case 'simplecache':
                $class = Handler\SimpleCache::class;
                $conn = Cache::simple($connect);
                break;

            case 'cache':
                $class = Handler\Cache::class;
                $conn = Cache::pool($connect);
                break;

            case 'db':
                $class = Handler\Database::class;
                $conn = Database::pdo($connect);
                break;

            default:
                return;
        }

        $settings = $config['settings'] ?? [];
        if (!isset($settings['lifetime']) && isset($this->cookieParams['lifetime'])) {
            $settings['lifetime'] = $this->cookieParams['lifetime'];
        }

        $this->handler = new $class($conn, $settings);
    }

    /**
     *
     * Gets a new session segment instance by name. Segments with the same
     * name will be different objects but will reference the same $_SESSION
     * values, so it is possible to have two or more objects that share state.
     * For good or bad, this a function of how $_SESSION works.
     *
     * @param string $name The name of the session segment, typically a
     *                     fully-qualified class name.
     *
     * @return Segment New Segment instance.
     *
     */
    public function getSegment($name)
    {
        return new Segment($this, $name);
    }

    /**
     *
     * Is the session already started?
     *
     * @return bool
     *
     */
    public function isStarted()
    {
        $started = session_status() === PHP_SESSION_ACTIVE;

        // if the session was started externally, move the flash values forward
        if ($started && !$this->flashMoved) {
            $this->moveFlash();
        }

        // done
        return $started;
    }

    /**
     *
     * Starts a new or existing session.
     *
     * @param array $options
     *
     * @return bool
     */
    public function start(array $options = null)
    {
        $sessionStatus = session_status();

        if ($sessionStatus === PHP_SESSION_DISABLED) {
            throw new \RuntimeException('PHP sessions are disabled');
        }

        if ($sessionStatus === PHP_SESSION_ACTIVE) {
            throw new \RuntimeException('session has already been started');
        }

        if ($this->handler) {
            session_set_save_handler($this->handler, true);
        }

        session_set_cookie_params(
            $this->cookieParams['lifetime'],
            $this->cookieParams['path'],
            $this->cookieParams['domain'],
            $this->cookieParams['secure'],
            $this->cookieParams['httponly']
        );

        if ($options === null) {
            $options = [];
        }

        if (!isset($options['session.serialize_handler'])) {
            $serializeHandler = ini_get('session.serialize_handler');
            if ($serializeHandler === 'php' || $serializeHandler === 'php_binary') {
                $options['session.serialize_handler'] = 'php_serialize';
            }
        }

        $result = session_start($options);

        if ($result) {
            $this->moveFlash();
        }

        return $result;
    }

    /**
     *
     * Moves the "next" flash values to the "now" values, thereby clearing the
     * "next" values.
     */
    protected function moveFlash()
    {
        if (!isset($_SESSION[Session::FLASH_NEXT])) {
            $_SESSION[Session::FLASH_NEXT] = [];
        }
        $_SESSION[Session::FLASH_NOW] = $_SESSION[Session::FLASH_NEXT];
        $_SESSION[Session::FLASH_NEXT] = [];
        $this->flashMoved = true;
    }

    /**
     *
     * Clears all session variables across all segments.
     *
     * @return null
     *
     */
    public function clear()
    {
        return session_unset();
    }

    /**
     *
     * Writes session data from all segments and ends the session.
     *
     * @return null
     *
     */
    public function commit()
    {
        return session_write_close();
    }

    /**
     *
     * Returns the CSRF token, creating it if needed (and thereby starting a
     * session).
     *
     * @return CsrfToken
     *
     */
    public function getCsrfToken()
    {
        if (!$this->csrfToken) {
            $segment = $this->getSegment(self::CSRF_TOKEN);
            $this->csrfToken = new CsrfToken($segment);
        }

        return $this->csrfToken;
    }

    /**
     *
     * Is a session available to be resumed?
     *
     * @return bool
     *
     */
    public function isResumable(): bool
    {
        $name = $this->getName();

        if ($this->request) {
            return $this->request->cookie($name) !== null;
        }

        return isset($_COOKIE[$name]);
    }


    /**
     *
     * Resumes a session, but does not start a new one if there is no
     * existing one.
     *
     * @return bool
     *
     */
    public function resume(): bool
    {
        if ($this->isStarted()) {
            return true;
        }

        if ($this->isResumable()) {
            return $this->start();
        }

        return false;
    }

    // =======================================================================
    //
    // support and admin methods
    //

    /**
     *
     * Sets the session cache expire time.
     *
     * @param int $expire The expiration time in seconds.
     *
     * @return int
     *
     * @see session_cache_expire()
     *
     */
    public function setCacheExpire($expire)
    {
        return session_cache_expire($expire);
    }

    /**
     *
     * Gets the session cache expire time.
     *
     * @return int The cache expiration time in seconds.
     *
     * @see session_cache_expire()
     *
     */
    public function getCacheExpire()
    {
        return session_cache_expire();
    }

    /**
     *
     * Sets the session cache limiter value.
     *
     * @param string $limiter The limiter value.
     *
     * @return string
     *
     * @see session_cache_limiter()
     *
     */
    public function setCacheLimiter($limiter)
    {
        return session_cache_limiter($limiter);
    }

    /**
     *
     * Gets the session cache limiter value.
     *
     * @return string The limiter value.
     *
     * @see session_cache_limiter()
     *
     */
    public function getCacheLimiter()
    {
        return session_cache_limiter();
    }

    /**
     *
     * Gets the session cookie params.
     *
     * @return array
     *
     */
    public function getCookieParams()
    {
        return $this->cookieParams;
    }

    /**
     *
     * Gets the current session id.
     *
     * @return string
     *
     */
    public function getId()
    {
        return session_id();
    }

    /**
     *
     * Regenerates and replaces the current session id; also regenerates the
     * CSRF token value if one exists.
     *
     * @return bool True if regeneration worked, false if not.
     *
     */
    public function regenerateId()
    {
        $result = session_regenerate_id(true);
        if ($result && $this->csrfToken) {
            $this->csrfToken->regenerateValue();
        }

        return $result;
    }

    /**
     *
     * Sets the current session name.
     *
     * @param string $name The session name to use.
     *
     * @return string
     *
     * @see session_name()
     *
     */
    public function setName($name)
    {
        return session_name($name);
    }

    /**
     *
     * Returns the current session name.
     *
     * @return string
     *
     */
    public function getName()
    {
        return session_name();
    }

    /**
     *
     * Sets the session save path.
     *
     * @param string $path The new save path.
     *
     * @return string
     *
     * @see session_save_path()
     *
     */
    public function setSavePath($path)
    {
        return session_save_path($path);
    }

    /**
     *
     * Gets the session save path.
     *
     * @return string
     *
     * @see session_save_path()
     *
     */
    public function getSavePath()
    {
        return session_save_path();
    }

    public function destroy()
    {
        if (!$this->isStarted()) {
            $this->start();
        }

        $name = $this->getName();
        $this->clear();

        $destroyed = session_destroy();

        if ($destroyed) {
            if ($this->response) {
                $this->response->cookie->delete($name);
            } else {
                setcookie($name, '', 0,
                    $this->cookieParams['path'],
                    $this->cookieParams['domain'],
                    $this->cookieParams['secure'],
                    $this->cookieParams['httponly']
                );
            }
        }

        return $destroyed;
    }

    public function get($key)
    {
        return $_SESSION[$key] ?? null;
    }

    public function has($key)
    {
        return isset($_SESSION[$key]);
    }

    public function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    public function delete($key)
    {
        unset($_SESSION[$key]);
    }
}
