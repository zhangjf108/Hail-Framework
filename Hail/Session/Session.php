<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */

namespace Hail\Session;

use Hail\Http\Request;
use Hail\Http\Response;

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
     * Incoming cookies from the client, typically a copy of the $_COOKIE
     * superglobal.
     *
     * @var array
     *
     */
    protected $cookies;

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
     * A callable to invoke when deleting the session cookie. The callable
     * should have the signature ...
     *
     *      function ($cookie_name, $cookieParams)
     *
     * ... and return null.
     *
     * @var callable|null
     *
     * @see setDeleteCookie()
     *
     */
    protected $deleteCookie;

    /**
     *
     * Have the flash values been moved forward?
     *
     * @var bool
     *
     */
    protected $flashMoved = false;

    /**
     *
     * Constructor
     *
     * @param array         $cookies               Optional: An array of cookies from the client, typically a
     *                                             copy of $_COOKIE. Empty array by default.
     *
     * @param callable|null $deleteCookie         Optional: An alternative callable
     *                                             to invoke when deleting the session cookie. Defaults to `null`.
     *
     */
    public function __construct(
        array $cookies = [],
        $deleteCookie = null
    ) {
        $this->cookies = $cookies;

        $this->setDeleteCookie($deleteCookie);

        $this->cookieParams = session_get_cookie_params();
    }

    /**
     *
     * Sets the delete-cookie callable.
     *
     * If parameter is `null`, the session cookie will be deleted using the
     * traditional way, i.e. using an expiration date in the past.
     *
     * @param callable|null $deleteCookie The callable to invoke when deleting the
     *                                     session cookie.
     *
     */
    public function setDeleteCookie($deleteCookie)
    {
        $this->deleteCookie = $deleteCookie;
        if (!$this->deleteCookie) {
            $this->deleteCookie = function ($name, $params) {
                setcookie(
                    $name,
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain']
                );
            };
        }
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
     * Is a session available to be resumed?
     *
     * @return bool
     *
     */
    public function isResumable()
    {
        $name = $this->getName();

        return isset($this->cookies[$name]);
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
     * @return bool
     *
     */
    public function start()
    {
        $result = session_start();
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
     * Resumes a session, but does not start a new one if there is no
     * existing one.
     *
     * @return bool
     *
     */
    public function resume()
    {
        if ($this->isStarted()) {
            return true;
        }

        if ($this->isResumable()) {
            return $this->start();
        }

        return false;
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
     * Destroys the session entirely.
     *
     * @return bool
     *
     * @see http://php.net/manual/en/function.session-destroy.php
     *
     */
    public function destroy()
    {
        if (!$this->isStarted()) {
            $this->start();
        }

        $name = $this->getName();
        $params = $this->getCookieParams();
        $this->clear();

        $destroyed = session_destroy();
        if ($destroyed) {
            call_user_func($this->deleteCookie, $name, $params);
        }

        return $destroyed;
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
     * Sets the session cookie params.  Param array keys are:
     *
     * - `lifetime` : Lifetime of the session cookie, defined in seconds.
     *
     * - `path` : Path on the domain where the cookie will work.
     *   Use a single slash ('/') for all paths on the domain.
     *
     * - `domain` : Cookie domain, for example 'www.php.net'.
     *   To make cookies visible on all subdomains then the domain must be
     *   prefixed with a dot like '.php.net'.
     *
     * - `secure` : If TRUE cookie will only be sent over secure connections.
     *
     * - `httponly` : If set to TRUE then PHP will attempt to send the httponly
     *   flag when setting the session cookie.
     *
     * @param array $params The array of session cookie param keys and values.
     *
     * @return null
     *
     * @see session_set_cookieParams()
     *
     */
    public function setCookieParams(array $params)
    {
        $this->cookieParams = array_merge($this->cookieParams, $params);
        session_set_cookie_params(
            $this->cookieParams['lifetime'],
            $this->cookieParams['path'],
            $this->cookieParams['domain'],
            $this->cookieParams['secure'],
            $this->cookieParams['httponly']
        );
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
}
