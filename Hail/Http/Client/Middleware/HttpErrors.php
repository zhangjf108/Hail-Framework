<?php

namespace Hail\Http\Client\Middleware;

use Hail\Http\Client\Exception\RequestException;
use Hail\Http\Client\MiddlewareInterface;
use Hail\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Middleware that throws exceptions for 4xx or 5xx responses when the
 * "http_error" request option is set to true.
 */
class HttpErrors implements MiddlewareInterface
{
    public function process(RequestInterface $request, array $options, callable $next): PromiseInterface
    {
        if (empty($options['http_errors'])) {
            return $next($request, $options);
        }

        return $next($request, $options)->then(
            function (ResponseInterface $response) use ($request) {
                $code = $response->getStatusCode();
                if ($code < 400) {
                    return $response;
                }
                throw RequestException::create($request, $response);
            }
        );
    }
}
