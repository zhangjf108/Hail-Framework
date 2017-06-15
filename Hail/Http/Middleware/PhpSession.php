<?php

namespace Hail\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\ServerMiddleware\DelegateInterface;
use RuntimeException;

class PhpSession implements MiddlewareInterface
{
    /**
     * @var string|null
     */
    private $name;

    /**
     * @var string|null
     */
    private $id;

    /**
     * @var array|null
     */
    private $options;

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
     * Configure the session id.
     *
     * @param string $id
     *
     * @return self
     */
    public function id($id)
    {
        $this->id = $id;

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
        $status = session_status();
        if ($status === PHP_SESSION_DISABLED) {
            throw new RuntimeException('PHP sessions are disabled');
        }

        if ($status === PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Failed to start the session: already started by PHP.');
        }

        //Session name
        if ($this->name) {
            $name = $this->name;
            session_name($name);
        } else {
            $name = session_name();
        }

        //Session id
        $id = $this->id;

        if (!$id) {
            $id = $request->getCookieParams()[$name] ?? null;
        }

        if (preg_match('/^[-,a-zA-Z0-9]{1,128}$/', $id) > 0) {
            session_id($id);
        }

        if ($this->options === null) {
            session_start();
        } else {
            session_start($this->options);
        }

        $response = $delegate->process($request);

        if ((session_status() === PHP_SESSION_ACTIVE) && (session_name() === $name)) {
            session_write_close();
        }

        return $response;
    }
}