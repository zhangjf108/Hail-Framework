<?php

namespace Hail;

use Hail\Container\Container;
use Hail\Exception\BadRequestException;
use Hail\Http\Factory;
use Hail\Http\HttpEvents;
use Hail\Http\Event\DispatcherEvent;
use Hail\Http\Exception\HttpErrorException;
use Hail\Http\Request;
use Psr\Http\Message\ResponseInterface;

class Application
{
    /**
     * @var Container
     */
    private $container;

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

    public function run()
    {
        $this->event(HttpEvents::DISPATCHER, [$this, 'changeRequest']);

        $server = Http\Server::createServer(
            $this->config('middleware'),
            $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
        );

        $server->listen($this->container);
    }

    public function changeRequest(DispatcherEvent $event): void
    {
        $this->get('request')->setServerRequest(
            $event->getRequest()
        );
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
        return $this->container->inject($name, $object);
    }

    /**
     * @param string $method
     * @param string $url
     *
     * @return ResponseInterface|array
     */
    public function dispatch(string $method, string $url)
    {
        /** @var Router $router */
        $router = $this->get('router');

        $result = $router->dispatch($method, $url);

        if (isset($result['error'])) {
            return Factory::response($result['error']);
        }

        /** @var Request $request */
        $request = $this->get('request');

        $request->routes($result['params'] ?? []);

        return $request->handler($result['handler']);
    }

    /**
     * @param array|\Closure $handler
     *
     * @return ResponseInterface
     * @throws BadRequestException
     */
    public function handle($handler): ResponseInterface
    {
        if ($handler instanceof \Closure) {
            return $this->call($handler);
        }

        [$class, $method] = $this->convert($handler);

        if ($this->has($class)) {
            $controller = $this->get($class);
        } else {
            $controller = $this->create($class);
            $this->inject($class, $controller);
        }

        $result = $this->container->call([$controller, $method]);

        if ($result instanceof ResponseInterface) {
            return $result;
        }

        return Factory::response(200);
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
}