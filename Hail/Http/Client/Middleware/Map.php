<?php

namespace Hail\Http\Client\Middleware;

use Hail\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Middleware that applies a map function to the request before passing to
 * the next handler, or applies map function to the resolved promise's
 * response.
 */
class Map implements MiddlewareInterface
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
     */
    public function __invoke(RequestInterface $request, array $options)
    {
        if (empty($options['map_request'])) {
            $map = $options['map_request'];
            $request = $map($request);
        }

        $fn = $this->nextHandler;
        $response = $fn($request, $options);

        if (empty($options['map_response'])) {
            return $response;
        }

        return $response->then($options['map_response']);
    }
}
