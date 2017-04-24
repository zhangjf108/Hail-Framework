<?php

namespace Hail;

use Hail\Container\Container;
use Hail\Http\Emitter\Sapi;
use Hail\Http\Event\DispatcherEvent;
use Hail\Http\HttpEvents;

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

        $response = $this->get('http.dispatcher')->dispatch(
            $this->get('http.request')
        );

        (new Sapi())->emit($response);
    }

    public function changeRequest(DispatcherEvent $event): void
    {
        $this->get('request')->changeServerRequest(
            $event->getRequest()
        );
    }
}