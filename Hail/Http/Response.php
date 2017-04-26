<?php

namespace Hail\Http;

use Hail\Application;
use Hail\Exception\BadRequestException;
use Hail\Facade\Output;

/**
 * Class Dispatcher
 *
 * @package Hail
 */
class Response
{
    /**
     * @var Application
     */
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function template(): string
    {
        $handler = $this->app->handler();

        if ($handler instanceof \Closure) {
            $template = $this->app->param('template');
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

    /**
     * @param array $to
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws BadRequestException
     */
    public function forward(array $to)
    {
        $this->app->params($to['params'] ?? null);

        return $this->app->handle(
            $this->app->handler($to)
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