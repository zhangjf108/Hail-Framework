<?php

namespace Hail\Http\Client\Middleware;

use Psr\Http\Message\RequestInterface;

interface MiddlewareInterface
{
    public function __construct(callable $nextHandler);

    public function __invoke(RequestInterface $request, array $options);
}