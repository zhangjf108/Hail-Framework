<?php

namespace Hail\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\ServerMiddleware\DelegateInterface;

class Route extends Controller
{
    /**
     * Process a server request and return a response.
     *
     * @param ServerRequestInterface $request
     * @param DelegateInterface      $delegate
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $handler = $this->request->dispatch(
            $request->getMethod(),
            $request->getUri()->getPath()
        );

        if ($handler instanceof ResponseInterface) {
            return $handler;
        }

        return $this->handle($handler);
    }
}