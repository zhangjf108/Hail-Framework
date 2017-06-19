<?php

namespace Hail\Http\Middleware;

use Hail\Session\Session as HailSession;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\ServerMiddleware\DelegateInterface;
use RuntimeException;

class Session implements MiddlewareInterface
{
    /**
     * @var string|null
     */
    private $name;

    /**
     * @var array|null
     */
    private $options;

    /**
     * @var HailSession
     */
    private $session;

    public function __construct(HailSession $session)
    {
        $this->session = $session;
    }

    /**
     * Configure the session name.
     *
     * @param string $name
     *
     * @return self
     */
    public function name($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Set the session options.
     *
     * @param array $options
     *
     * @return self
     */
    public function options(array $options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Process a server request and return a response.
     *
     * @param ServerRequestInterface $request
     * @param DelegateInterface      $delegate
     *
     * @return ResponseInterface
     * @throws RuntimeException
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        //Session name
        if ($this->name) {
            $name = $this->name;
            $this->session->setName($name);
        } else {
            $name = $this->session->getName();
        }

        $this->session->start($this->options);

        $response = $delegate->process($request);

        if ($this->session->isStarted() && ($this->session->getName() === $name)) {
            $this->session->commit();
        }

        return $response;
    }
}