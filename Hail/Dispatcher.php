<?php

namespace Hail;

use Hail\Http\Dispatcher as HttpDispatcher;
use Hail\Facade\Output;
use Hail\Http\Middleware\Controller;

/**
 * Class Dispatcher
 * @package Hail
 */
class Dispatcher
{
    /**
     * @var HttpDispatcher
     */
    protected $dispatcher;

    /**
     * @var Request
     */
    protected $request;

    public function __construct(HttpDispatcher $dispatcher, Request $request)
    {
        $this->dispatcher = $dispatcher;
        $this->request = $request;
    }

    public function template(): string
    {
        $handler = $this->request->handler();

        if ($handler instanceof \Closure) {
            $template = $this->request->route('template');
            if ($template === null) {
                throw new \LogicException('Template name not defined!');
            }
        }

        return ltrim($handler['app'] . '/' . $handler['controller'] . '/' . $handler['action'], '/');
    }

    public function output($type, $return)
    {
        if ($return === null || $return === false) {
            return;
        }

        if ($return === true) {
            $return = [];
        }

        switch ($type) {
            case 'json':
                if (!is_array($return)) {
                    $return = ['ret' => 0, 'msg' => is_string($return) ? $return : 'OK'];
                } else {
                    if (!isset($return['ret'])) {
                        $return['ret'] = 0;
                        $return['msg'] = '';
                    }
                }

                Output::json()->send($return);
                break;

            case 'text':
                Output::text()->send($return);
                break;

            case 'template':
                Output::template()->send($this->template(), $return);
                break;
        }
    }

    public function forward($to)
    {
        $this->request->routes($to['params'] ?? null);
        $this->request->handler($to);

        $this->dispatcher->after(Controller::class);

        return null;
    }

    public function error($code, $msg = null)
    {
        return $this->forward([
            'controller' => 'Error',
            'params' => [
                'error' => $code,
                'message' => $msg,
            ],
        ]);
    }
}