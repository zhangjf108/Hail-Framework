<?php

namespace Hail\Http\Middleware;

use Hail\Request;
use Hail\Container\Container;
use Hail\Http\Exception\HttpErrorException;
use Hail\Http\Factory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\ServerMiddleware\DelegateInterface;

class Controller implements MiddlewareInterface
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Container
     */
    protected $container;

    /**
     * @param Container $container
     * @param Request   $request
     */
    public function __construct(Container $container, Request $request = null)
    {
        $this->container = $container;

        if ($request === null) {
            if ($container->has('request')) {
                $request = $container->get('request');
            } else {
                throw new \InvalidArgumentException('Request not define!');
            }
        }

        $this->request = $request;
    }

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
        return $this->handle(
            $this->request->handler()
        );
    }

    /**
     * @param array|\Closure $handler
     *
     * @return ResponseInterface
     */
    protected function handle($handler): ResponseInterface
    {
        if ($handler instanceof \Closure) {
            return $this->container->call($handler);
        }

        [$class, $method] = $this->convert($handler);

        if ($this->container->has($class)) {
            $controller = $this->container->get($class);
        } else {
            $controller = $this->container->create($class);
            $this->container->inject($class, $controller);
        }

        $result = $this->container->call([$controller, $method]);

        if ($result instanceof ResponseInterface) {
            return $result;
        }

        return Factory::response(200);
    }

    protected function getNamespace(array $handler): string
    {
        $namespace = 'App\\Controller';

        if ($app = $handler['app']) {
            $namespace .= '\\' . ucfirst($app);
        }

        return $namespace;
    }

    /**
     * @param array $handler
     *
     * @return array
     * @throws HttpErrorException
     */
    protected function convert(array $handler): array
    {
        $class = $this->class($handler);

        $action = $handler['action'] ?? 'index';
        $actionClass = $class . '\\' . ucfirst($action);

        if (class_exists($actionClass)) {
            $class = $actionClass;
            $method = '__invoke';
        } elseif (class_exists($class)) {
            $method = lcfirst($action) . 'Action';
        }

        if (!isset($method) || !method_exists($class, $method)) {
            throw HttpErrorException::create(404, [
                'controller' => $class,
                'action' => $method ?? $action,
            ]);
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
        $namespace = $this->getNamespace($handler);
        $class = $handler['controller'] ?? 'Index';

        return strpos($class, $namespace) === 0 ? $class : $namespace . '\\' . ucfirst($class);
    }
}