<?php

namespace Hail\Http\Client\Middleware;

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
    /** @var callable  */
    private $nextHandler;

    /**
     * @param callable $nextHandler Next handler to invoke.
     */
    public function __construct(callable $nextHandler) {
        $this->nextHandler = $nextHandler;
    }

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

    /**
     * @param RequestInterface $request
     * @param array            $options
     *
     * @return PromiseInterface
     */
    public function __invoke(RequestInterface $request, array $options)
    {
        $fn = $this->nextHandler;
        if (!isset($options['retry_decider'])) {
            return $fn($request, $options);
        }

        if (!isset($options['retry_delay'])) {
            $options['retry_delay'] = [__CLASS__, 'exponentialDelay'];
        }

        if (!isset($options['retries'])) {
            $options['retries'] = 0;
        }

        return $fn($request, $options)
            ->then(
                $this->onFulfilled($request, $options),
                $this->onRejected($request, $options)
            );
    }

    private function onFulfilled(RequestInterface $req, array $options)
    {

        return function ($value) use ($req, $options) {
            $decider = $options['retry_decider'];
            if (!$decider($options['retries'], $req, $value, null)) {
                return $value;
            }

            return $this->doRetry($req, $options, $value);
        };
    }

    private function onRejected(RequestInterface $req, array $options)
    {
        return function ($reason) use ($req, $options) {
            $decider = $options['retry_decider'];
            if (!$decider($options['retries'], $req, null, $reason)) {
                return Factory::rejection($reason);
            }

            return $this->doRetry($req, $options);
        };
    }

    private function doRetry(RequestInterface $request, array $options, ResponseInterface $response = null)
    {
        $delay = $options['retry_delay'];
        $options['delay'] = $delay(++$options['retries'], $response);

        return $this($request, $options);
    }
}
