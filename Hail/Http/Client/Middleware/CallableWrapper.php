<?php

namespace Hail\Http\Client\Middleware;

use Hail\Http\Client\MiddlewareInterface;
use Hail\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;

class CallableWrapper implements MiddlewareInterface
{
    /**
     * @var callable
     */
    private $handler;

    /**
     * Constructor.
     *
     * @param callable $handler
     */
    public function __construct(callable $handler)
    {
        $this->handler = $handler;
    }

    public function process(RequestInterface $request, array $options, callable $next): PromiseInterface
    {
        return ($this->handler)($request, $options, $next);
    }
}