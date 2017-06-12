<?php

namespace Hail\Http\Client;

use Hail\Promise\PromiseInterface;
use Hail\Util\SingletonTrait;
use Psr\Http\Message\RequestInterface;

class HandlerStack
{
    use SingletonTrait;

    /**
     * @var array
     */
    private $middleware;

    /**
     * @var int
     */
    private $index;

    public function init()
    {
        $this->middleware = [
            Middleware\Map::class,
            Middleware\Log::class,
            Middleware\Retry::class,
            Middleware\HttpErrors::class,
            Middleware\Redirect::class,
            Middleware\Cookies::class,
            Middleware\PrepareBody::class,
        ];
    }

    /**
     * Return the next available middleware frame in the queue.
     *
     * @return MiddlewareInterface|false
     * @throws \LogicException
     */
    public function next(): ?MiddlewareInterface
    {
        ++$this->index;

        return $this->get();
    }

    /**
     * Dispatch the request, return a promise.
     *
     * @param RequestInterface $request
     * @param array            $options
     *
     * @return PromiseInterface
     * @throws \LogicException
     */
    public function process(RequestInterface $request, array $options): PromiseInterface
    {
        $this->index = 0;

        return $this->get()->process($request, $options, $this);
    }

    public function __invoke(RequestInterface $request, array $options): PromiseInterface
    {
        $middleware = $this->next();
        if ($middleware === null) {
            throw new \LogicException('Middleware queue exhausted, with no response returned.');
        }

        return $middleware->process($request, $options, $this);
    }

    /**
     * Return the next available middleware frame in the middleware.
     *
     * @return MiddlewareInterface
     * @throws \LogicException
     */
    public function get(): ?MiddlewareInterface
    {
        if (!isset($this->middleware[$this->index])) {
            return null;
        }

        $middleware = $this->middleware[$this->index];

        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        if (is_string($middleware)) {
            $middleware = new $middleware();

            if (!$middleware instanceof MiddlewareInterface) {
                throw new \LogicException('The middleware must be an instance of MiddlewareInterface');
            }
        } elseif (is_callable($middleware)) {
            $middleware = new Middleware\CallableWrapper($middleware);
        }

        return $this->middleware[$this->index] = $middleware;
    }
}
