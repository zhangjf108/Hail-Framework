<?php

namespace Hail;

use Hail\Container\Container;
use Hail\Http\{
    Response, Server, Emitter\EmitterInterface
};
use Hail\Exception\BadRequestException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Application
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * @var Server
     */
    protected $server;

    /**
     * @var array
     */
    protected $params = [];

    /**
     * @var array|\Closure
     */
    protected $handler;

    protected static $default = [
        'app' => null,
        'controller' => 'Index',
        'action' => 'index',
    ];

    /**
     * Application constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function get(string $name)
    {
        return $this->container->get($name);
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function config(string $name)
    {
        return $this->get('config')->get($name);
    }

    /**
     * @param string   $eventName
     * @param callable $callable
     */
    public function event(string $eventName, callable $callable): void
    {
        $this->get('event')->attach($eventName, $callable);
    }

    /**
     * @return Server
     */
    public function getServer(): Server
    {
        if ($this->server === null) {
            $this->server = Server::createServer(
                $this->config('middleware'),
                $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
            );

            $this->setRequest(
                $this->server->getRequest()
            );
        }

        return $this->server;
    }

    public function listen()
    {
        $this->getServer()->listen($this->container);
    }

    /**
     * Set alternate response emitter to use.
     *
     * @param EmitterInterface $emitter
     */
    public function emitter(EmitterInterface $emitter): void
    {
        $this->server->setEmitter($emitter);
    }

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->get('request')->setServerRequest($request);
    }

    public function call(callable $callback, array $map = [])
    {
        return $this->container->call($callback, $map);
    }

    public function create(string $class, array $map = [])
    {
        return $this->container->create($class, $map);
    }

    public function has(string $name)
    {
        return $this->container->has($name);
    }

    public function inject(string $name, $object)
    {
        $this->container->inject($name, $object);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface|array
     * @throws BadRequestException
     */
    public function dispatch(ServerRequestInterface $request)
    {
        $this->setRequest($request);

        /** @var Router $router */
        $router = $this->get('router');
        $result = $router->dispatch(
            $request->getMethod(),
            $request->getUri()->getPath()
        );

        if (isset($result['error'])) {
            throw new BadRequestException('Router not found', $result['error']);
        }

        $this->params($result['params'] ?? []);

        return $this->handler($result['handler']);
    }

    /**
     * @param array|\Closure $handler
     *
     * @return ResponseInterface
     * @throws BadRequestException
     */
    public function handle($handler): ResponseInterface
    {
        if (!$handler instanceof \Closure) {
            [$class, $method] = $this->convert($handler);

            if ($this->has($class)) {
                $controller = $this->get($class);
            } else {
                $controller = $this->create($class);
                $this->inject($class, $controller);
            }

            $handler = [$controller, $method];
        }

        $result = $this->call($handler);

        if ($result instanceof ResponseInterface) {
            return $result;
        }

        /** @var Response $response */
        $response = $this->get('response');

        return $response->default($result);
    }

    /**
     * @param array $handler
     *
     * @return array
     * @throws BadRequestException
     */
    protected function convert(array $handler): array
    {
        $class = $this->class($handler);

        $action = $handler['action'] ?? 'index';
        $actionClass = $class . '\\' . ucfirst($action);

        if (class_exists($actionClass)) {
            $class = $actionClass;
            $method = '__invoke';
        } else {
            $method = lcfirst($action) . 'Action';
        }

        if (!method_exists($class, $method)) {
            throw new BadRequestException("Controller not defined: {$class}::{$method}", 404);
        }

        return [$class, $method];
    }

    /**
     * @param array $handler
     *
     * @return string
     */
    protected function class(array $handler): string
    {
        $namespace = 'App\\Controller';

        if ($app = $handler['app']) {
            $namespace .= '\\' . ucfirst($app);
        }

        $class = $handler['controller'] ?? 'Index';

        return strpos($class, $namespace) === 0 ? $class : $namespace . '\\' . ucfirst($class);
    }

    /**
     * Get param from router
     *
     * @param string $name
     * @param mixed  $value
     *
     * @return mixed|null
     */
    public function param(string $name, $value = null)
    {
        if ($value === null) {
            return $this->params[$name] ?? null;
        }

        return $this->params[$name] = $value;
    }

    /**
     * Get all params from router
     *
     * @param array|null $array
     *
     * @return array
     */
    public function params(array $array = null): array
    {
        if ($array === null) {
            return $this->params;
        }

        return $this->params = $array;
    }

    /**
     * @param array|null $handler
     *
     * @return array|\Closure
     */
    public function handler(array $handler = null)
    {
        if ($handler !== null) {
            if ($handler instanceof \Closure) {
                $this->handler = $handler;
            } else {
                foreach (static::$default as $k => $v) {
                    $this->handler[$k] = $handler[$k] ?? $v;
                }
            }
        }

        return $this->handler;
    }

    public function render(ResponseInterface $response, string $name, array $params = []): ResponseInterface
    {
        /** @var Template\Engine $template */
        $template = $this->get('template');

        $response->getBody()->write(
            $template->capture($name, $params)
        );

        return $response;
    }
}