<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace Hail\Http;

use Hail\Container\Container;
use Hail\Http\Emitter\EmitterInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * "Serve" incoming HTTP requests
 *
 * Given a callback, takes an incoming request, dispatches it to the
 * callback, and then sends a response.
 */
class Server
{
    /**
     * @var array
     */
    private $middleware;

    /**
     * Response emitter to use; by default, uses Emitter\Sapi.
     *
     * @var EmitterInterface
     */
    private $emitter;

    /**
     * @var ServerRequestInterface
     */
    private $request;


    /**
     * Constructor
     *
     * Given a callback, a request, and a response, we can create a server.
     *
     * @param array                  $middleware
     * @param ServerRequestInterface $request
     */
    public function __construct(array $middleware, ServerRequestInterface $request)
    {
        $this->middleware = $middleware;
        $this->request = $request;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * Set alternate response emitter to use.
     *
     * @param EmitterInterface $emitter
     */
    public function setEmitter(EmitterInterface $emitter): void
    {
        $this->emitter = $emitter;
    }

    /**
     * Create a Server instance
     *
     * Creates a server instance from the callback and the following
     * PHP environmental values:
     *
     * - server; typically this will be the $_SERVER superglobal
     * - query; typically this will be the $_GET superglobal
     * - body; typically this will be the $_POST superglobal
     * - cookies; typically this will be the $_COOKIE superglobal
     * - files; typically this will be the $_FILES superglobal
     *
     * @param array          $middleware
     * @param array          $server
     * @param array          $query
     * @param array          $body
     * @param array          $cookies
     * @param array          $files
     *
     * @return static
     */
    public static function createServer(
        array $middleware,
        array $server = null,
        array $query = null,
        array $body = null,
        array $cookies = null,
        array $files = null
    ) {
        $request = Helpers::createServer(
            $server, $query, $body, $cookies, $files
        );

        return new static($middleware, $request);
    }

    /**
     * Create a Server instance from an existing request object
     *
     * Provided a callback, an existing request object, and optionally an
     * existing response object, create and return the Server instance.
     *
     * @param array                  $middleware
     * @param ServerRequestInterface $request
     *
     * @return static
     */
    public static function createServerFromRequest(
        array $middleware,
        ServerRequestInterface $request
    ) {
        return new static($middleware, $request);
    }

    /**
     * "Listen" to an incoming request
     *
     * Output buffering is enabled prior to invoking the attached
     * callback; any output buffered will be sent prior to any
     * response body content.
     *
     * @param Container $container
     */
    public function listen(Container $container = null)
    {
        ob_start();
        $bufferLevel = ob_get_level();

        $dispatcher = new Dispatcher($this->middleware, $container);

        $response = $dispatcher->dispatch($this->request);

        $this->getEmitter()->emit($response, $bufferLevel);
    }

    /**
     * Retrieve the current response emitter.
     *
     * If none has been registered, lazy-loads a Emitter\Sapi.
     *
     * @return EmitterInterface
     */
    private function getEmitter(): EmitterInterface
    {
        if (!$this->emitter) {
            $this->emitter = new Emitter\Sapi();
        }

        return $this->emitter;
    }
}