<?php

namespace Hail\Http\Client\Middleware;

use Hail\Http\Client\Cookie\CookieJarInterface;
use Hail\Http\Client\MiddlewareInterface;
use Hail\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Middleware that adds cookies to requests.
 *
 * The options array must be set to a CookieJarInterface in order to use
 * cookies. This is typically handled for you by a client.
 */
class Cookies implements MiddlewareInterface
{
    public function process(RequestInterface $request, array $options, callable $next): PromiseInterface
    {
        if (empty($options['cookies'])) {
            return $next($request, $options);
        }

        if (!($options['cookies'] instanceof CookieJarInterface)) {
            throw new \InvalidArgumentException('cookies must be an instance of Hail\Http\Client\Cookie\CookieJarInterface');
        }

        $cookieJar = $options['cookies'];
        $request = $cookieJar->withCookieHeader($request);

        return $next($request, $options)->then(
            function ($response) use ($cookieJar, $request) {
                $cookieJar->extractCookies($request, $response);

                return $response;
            }
        );
    }
}
