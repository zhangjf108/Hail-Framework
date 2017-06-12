<?php

namespace Hail\Http\Client\Middleware;

use Hail\Http\Client\MiddlewareInterface;
use Hail\Promise\Factory;
use Hail\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Middleware that retries requests based on the boolean result of
 * invoking the provided "decider" function.
 */
class Retry implements MiddlewareInterface
{
    /**
     * Default exponential backoff delay function.
     *
     * @param $retries
     *
     * @return int
     */
    public static function exponentialDelay($retries): int
    {
        return (int) 2 ** ($retries - 1);
    }

    public function process(RequestInterface $request, array $options, callable $next): PromiseInterface
    {
        if (!isset($options['retry_decider'])) {
            return $next($request, $options);
        }

        if (!isset($options['retry_delay'])) {
            $options['retry_delay'] = [__CLASS__, 'exponentialDelay'];
        }

        if (!isset($options['retries'])) {
            $options['retries'] = 0;
        }

        return $next($request, $options)
            ->then(
                $this->onFulfilled($request, $options, $next),
                $this->onRejected($request, $options, $next)
            );
    }

    private function onFulfilled(RequestInterface $req, array $options, callable $next)
    {
        return function ($value) use ($req, $options, $next) {
            $decider = $options['retry_decider'];
            if (!$decider($options['retries'], $req, $value, null)) {
                return $value;
            }

            return $this->doRetry($req, $options, $next, $value);
        };
    }

    private function onRejected(RequestInterface $req, array $options, callable $next)
    {
        return function ($reason) use ($req, $options, $next) {
            $decider = $options['retry_decider'];
            if (!$decider($options['retries'], $req, null, $reason)) {
                return Factory::rejection($reason);
            }

            return $this->doRetry($req, $options, $next);
        };
    }

    private function doRetry(RequestInterface $request, array $options, callable $next, ResponseInterface $response = null)
    {
        $delay = $options['retry_delay'];
        $options['delay'] = $delay(++$options['retries'], $response);

        return $this->process($request, $options, $next);
    }
}
