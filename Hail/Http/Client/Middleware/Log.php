<?php

namespace Hail\Http\Client\Middleware;

use Hail\Http\Client\Exception\RequestException;
use Hail\Http\Client\MessageFormatter;
use Hail\Promise\PromiseInterface;
use Hail\Promise\Factory;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LogLevel;

/**
 * Middleware that logs requests, responses, and errors using a message formatter.
 */
class Log implements MiddlewareInterface
{
    /** @var callable */
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
        $fn = $this->nextHandler;
        if (empty($options['logger'])) {
            return $fn($request, $options);
        }

        $logger = $options['logger'];
        $formatter = new MessageFormatter($options['log_format'] ?? null);
        $logLevel = $options['log_level'] ?? LogLevel::INFO;

        return $fn($request, $options)->then(
            function ($response) use ($logger, $request, $formatter, $logLevel) {
                $message = $formatter->format($request, $response);
                $logger->log($logLevel, $message);

                return $response;
            },
            function ($reason) use ($logger, $request, $formatter) {
                $response = $reason instanceof RequestException
                    ? $reason->getResponse()
                    : null;
                $message = $formatter->format($request, $response, $reason);
                $logger->notice($message);

                return Factory::rejection($reason);
            }
        );
    }
}
