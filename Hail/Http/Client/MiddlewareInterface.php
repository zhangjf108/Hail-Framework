<?php

namespace Hail\Http\Client;

use Hail\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;

interface MiddlewareInterface
{
    /**
     * Process a client request and return a promise.
     *
     * @param RequestInterface $request
     * @param array            $options
     * @param callable         $next
     *
     * @return PromiseInterface
     */
    public function process(RequestInterface $request, array $options, callable $next): PromiseInterface;
}