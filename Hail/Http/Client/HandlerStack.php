<?php

namespace Hail\Http\Client;

use Psr\Http\Message\RequestInterface;

/**
 * Creates a composed Guzzle handler function by stacking middlewares on top of
 * an HTTP handler function.
 */
class HandlerStack
{
    /** @var callable */
    private $handler;

    /** @var array */
    private $stack = [];

    /** @var callable|null */
    private $cached;

    /**
     * @param callable $handler Underlying HTTP handler.
     */
    public function __construct(callable $handler = null)
    {
        $this->handler = $handler;
    }

    public static function default()
    {
        $handler = new static();

        $handler->stack = [
            [Middleware\Map::class, 'map'],
            [Middleware\Log::class, 'log'],
            [Middleware\Retry::class, 'retry'],
            [Middleware\HttpErrors::class, 'http_errors'],
            [Middleware\Redirect::class, 'allow_redirects'],
            [Middleware\Cookies::class, 'cookies'],
            [Middleware\PrepareBody::class, 'prepare_body'],
        ];

        return $handler;
    }

    /**
     * Invokes the handler stack as a composed handler
     *
     * @param RequestInterface $request
     * @param array            $options
     */
    public function __invoke(RequestInterface $request, array $options)
    {
        $handler = $this->resolve();

        return $handler($request, $options);
    }

    /**
     * Unshift a middleware to the bottom of the stack.
     *
     * @param callable $middleware Middleware function
     * @param string   $name       Name to register for this middleware.
     */
    public function unshift(callable $middleware, $name = null)
    {
        array_unshift($this->stack, [$middleware, $name]);
        $this->cached = null;
    }

    /**
     * Push a middleware to the top of the stack.
     *
     * @param callable $middleware Middleware function
     * @param string   $name       Name to register for this middleware.
     */
    public function push(callable $middleware, $name = '')
    {
        $this->stack[] = [$middleware, $name];
        $this->cached = null;
    }

    /**
     * Add a middleware before another middleware by name.
     *
     * @param string   $findName   Middleware to find
     * @param callable $middleware Middleware function
     * @param string   $withName   Name to register for this middleware.
     */
    public function before($findName, callable $middleware, $withName = '')
    {
        $this->splice($findName, [$middleware, $withName], true);
    }

    /**
     * Add a middleware after another middleware by name.
     *
     * @param string   $findName   Middleware to find
     * @param callable $middleware Middleware function
     * @param string   $withName   Name to register for this middleware.
     */
    public function after($findName, callable $middleware, $withName = '')
    {
        $this->splice($findName, [$middleware, $withName], false);
    }

    /**
     * Remove a middleware by instance or name from the stack.
     *
     * @param callable|string $remove Middleware to remove by instance or name.
     */
    public function remove($remove)
    {
        $this->cached = null;
        $idx = is_callable($remove) ? 0 : 1;
        $this->stack = array_values(array_filter(
            $this->stack,
            function ($tuple) use ($idx, $remove) {
                return $tuple[$idx] !== $remove;
            }
        ));
    }

    /**
     * Compose the middleware and handler into a single callable function.
     *
     * @return callable
     */
    public function resolve()
    {
        if (!$this->cached) {
            $prev = $this->handler ?? Helpers::chooseHandler();

            foreach (array_reverse($this->stack) as $fn) {
                $fn = $fn[0];
                if ($fn instanceof Middleware\MiddlewareInterface) {
                    $prev = new $fn($prev);
                } else {
                    $prev = $fn($prev);
                }
            }

            $this->cached = $prev;
        }

        return $this->cached;
    }

    /**
     * @param $name
     *
     * @return int
     */
    private function findByName($name)
    {
        foreach ($this->stack as $k => $v) {
            if ($v[1] === $name) {
                return $k;
            }
        }

        throw new \InvalidArgumentException("Middleware not found: $name");
    }

    /**
     * Splices a function into the middleware list at a specific position.
     *
     * @param          $findName
     * @param          $tuple
     * @param          $before
     */
    private function splice($findName, $tuple, $before)
    {
        $this->cached = null;
        $idx = $this->findByName($findName);

        if ($before) {
            if ($idx === 0) {
                array_unshift($this->stack, $tuple);
            } else {
                $replacement = [$tuple, $this->stack[$idx]];
                array_splice($this->stack, $idx, 1, $replacement);
            }
        } elseif ($idx === count($this->stack) - 1) {
            $this->stack[] = $tuple;
        } else {
            $replacement = [$this->stack[$idx], $tuple];
            array_splice($this->stack, $idx, 1, $replacement);
        }
    }
}
