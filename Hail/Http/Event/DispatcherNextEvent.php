<?php

namespace Hail\Http\Event;

use Hail\Event\Event;
use Hail\Http\HttpEvents;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class DispatcherNextEvent
 * @package Hail\Http\Event
 */
class DispatcherNextEvent extends Event
{
    public function __construct()
    {
        parent::__construct(HttpEvents::DISPATCHER_NEXT);
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->getParam('request');
    }

    public function setRequest(ServerRequestInterface $request): self
    {
        $this->setParam('request', $request);

        return $this;
    }
}