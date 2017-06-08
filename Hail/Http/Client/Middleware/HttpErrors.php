<?php

namespace Hail\Http\Client\Middleware;

use Hail\Http\Client\Exception\RequestException;
use Hail\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Middleware that throws exceptions for 4xx or 5xx responses when the
 * "http_error" request option is set to true.
 */
class HttpErrors implements MiddlewareInterface
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
     * @throws RequestException
     */
    public function __invoke(RequestInterface $request, array $options)
    {
        $fn = $this->nextHandler;
        if (empty($options['http_errors'])) {
            return $fn($request, $options);
        }

        return $fn($request, $options)->then(
            function (ResponseInterface $response) use ($request) {
                $code = $response->getStatusCode();
                if ($code < 400) {
                    return $response;
                }
                throw RequestException::create($request, $response);
            }
        );
    }
}
