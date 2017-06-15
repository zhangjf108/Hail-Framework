<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
namespace Hail\Session;

/**
 *
 * A factory to create a Session manager.
 *
 * @package Aura.Session
 *
 */
class SessionFactory
{
    /**
     *
     * Creates a new Session manager.
     *
     * @param array $cookies An array of cookie values, typically $_COOKIE.
     *
     * @param callable|null $delete_cookie Optional: An alternative callable
     * to invoke when deleting the session cookie. Defaults to `null`.
     *
     * @return Session New Session manager instance
     */
    public function newInstance(array $cookies, $delete_cookie = null)
    {
        return new Session(
            $cookies,
            $delete_cookie
        );
    }
}
