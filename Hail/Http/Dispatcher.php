<?php

namespace Hail\Http;

use Psr\Container\ContainerInterface;
use Psr\Http\{
    ServerMiddleware\DelegateInterface,
    ServerMiddleware\MiddlewareInterface,
    Message\ResponseInterface,
    Message\ServerRequestInterface
};

/**
 * PSR-15 middleware dispatcher
 */
class Dispatcher implements DelegateInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var array
     */
    private $middleware;

    /**
     * @var int
     */
    private $index = 0;

    /**
     * @param (callable|MiddlewareInterface|mixed)[] $middleware middleware stack (with at least one middleware component)
     * @param ContainerInterface|null $container optional middleware resolver:
     *                                           $container->get(string $name): MiddlewareInterface
     *
     * @throws \InvalidArgumentException if an empty middleware stack was given
     */
    public function __construct(array $middleware, ContainerInterface $container = null)
    {
        if (empty($middleware)) {
            throw new \InvalidArgumentException('Empty middleware queue');
        }

        $this->middleware = $middleware;
        $this->container = $container;
    }

    /**
     * Dispatch the request, return a response.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     * @throws \LogicException
     */
    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        return $this->get()->process($request, $this);
    }


    /**
     * {@inheritdoc}
     * @throws \LogicException
     */
    public function process(ServerRequestInterface $request): ResponseInterface
    {
        $middleware = $this->get();
        if ($middleware === null) {
            throw new \LogicException('Middleware queue exhausted, with no response returned.');
        }

        return $middleware->process($request, $this);
    }

    /**
     * Return the next available middleware frame in the middleware.
     *
     * @return MiddlewareInterface|null
     * @throws \LogicException
     */
    protected function get(): ?MiddlewareInterface
    {
        $index = $this->index++;

        if (!isset($this->middleware[$index])) {
            return null;
        }

        $middleware = $this->middleware[$index];

        if (is_callable($middleware)) {
            $middleware = new Middleware\CallableWrapper($middleware);
        } elseif (is_string($middleware)) {
            if ($this->container === null) {
                throw new \LogicException("No valid middleware provided: $middleware");
            }

            $middleware = $this->container->get($middleware);
        }

        if (!$middleware instanceof MiddlewareInterface) {
            throw new \LogicException('The middleware must be an instance of MiddlewareInterface');
        }

        return $middleware;
    }
}
