<?php

namespace Hail\Http;

use Hail\Application;
use Hail\Facade\Output;

/**
 * Class Dispatcher
 * @package Hail
 */
class Response
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var Request
     */
    protected $request;

    public function __construct(Application $app, Request $request = null)
    {
        $this->app = $app;

        if ($request === null) {
            $request = $this->app->get('request');
        }

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

        return $this->app->handle(
            $this->request->handler($to)
        );
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