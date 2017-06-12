<?php

namespace Hail\Http\Client\Middleware;

use Hail\Promise\PromiseInterface;
use Hail\Http\Client\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Middleware that applies a map function to the request before passing to
 * the next handler, or applies map function to the resolved promise's
 * response.
 */
class Map implements MiddlewareInterface
{
    public function process(RequestInterface $request, array $options, callable $next): PromiseInterface
    {
        if (empty($options['map_request'])) {
            $map = $options['map_request'];
            $request = $map($request);
        }

        $promise = $next($request, $options);

        if (empty($options['map_response'])) {
            return $promise;
        }

        return $promise->then($options['map_response']);
    }
}
