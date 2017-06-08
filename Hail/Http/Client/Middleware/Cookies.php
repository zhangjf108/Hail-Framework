<?php

namespace Hail\Http\Client\Middleware;

use Hail\Http\Client\Cookie\CookieJarInterface;
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
    /** @var callable  */
    private $nextHandler;

    /**
     * @param callable $nextHandler Next handler to invoke.
     */
    public function __construct(callable $nextHandler)
    {
        $this->nextHandler = $nextHandler;
    }

    /**
     * @param RequestInterface $request
     * @param array            $options
     *
     * @return PromiseInterface
     * @throws \InvalidArgumentException
     */
    public function __invoke(RequestInterface $request, array $options)
    {
        $fn = $this->nextHandler;
        if (empty($options['cookies'])) {
            return $fn($request, $options);
        }

        if (!($options['cookies'] instanceof CookieJarInterface)) {
            throw new \InvalidArgumentException('cookies must be an instance of Hail\Http\Client\Cookie\CookieJarInterface');
        }

        $cookieJar = $options['cookies'];
        $request = $cookieJar->withCookieHeader($request);

        return $fn($request, $options)->then(
            function ($response) use ($cookieJar, $request) {
                $cookieJar->extractCookies($request, $response);

                return $response;
            }
        );
    }
}
