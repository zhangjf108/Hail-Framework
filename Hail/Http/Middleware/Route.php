<?php

namespace Hail\Http\Middleware;

use Hail\Application;
use Hail\Exception\BadRequestException;
use Hail\Http\Exception\HttpErrorException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\ServerMiddleware\DelegateInterface;
use Psr\Http\ServerMiddleware\MiddlewareInterface;

class Route implements MiddlewareInterface
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Process a server request and return a response.
     *
     * @param ServerRequestInterface $request
     * @param DelegateInterface      $delegate
     *
     * @return ResponseInterface
     * @throws HttpErrorException
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        try {
            $handler = $this->app->dispatch(
                $request->getMethod(),
                $request->getUri()->getPath()
            );

            if ($handler instanceof ResponseInterface) {
                return $handler;
            }

            return $this->app->handle($handler);
        } catch (BadRequestException $e) {
            throw HttpErrorException::create($e->getCode(), [
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
            ], $e);
        }
    }
}