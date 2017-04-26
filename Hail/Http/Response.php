<?php

namespace Hail\Http;

use Hail\Application;
use Hail\Exception\BadRequestException;
use Hail\Facade\Output;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

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

    /**
     * @param string|UriInterface $uri
     * @param int                 $status
     * @param array               $headers
     *
     * @return ResponseInterface
     */
    public function redirect($uri, $status = 302, array $headers = []): ResponseInterface
    {
        if (!is_string($uri) && !$uri instanceof UriInterface) {
            throw new \InvalidArgumentException('Uri MUST be a string or Psr\Http\Message\UriInterface instance; received "' .
                (is_object($uri) ? get_class($uri) : gettype($uri)) . '"');
        }

        $headers['Location'] = [(string) $uri];

        return Factory::response($status, null, $headers);
    }

    /**
     * Get or set template name
     *
     * @param string|null $name null use for get name
     *
     * @return string
     */
    public function template(string $name = null): string
    {
        if ($name === null) {
            $handler = $this->app->handler();

            if ($handler instanceof \Closure) {
                $template = $this->app->param('template');
                if ($template === null) {
                    throw new \LogicException('Controller not defined template name!');
                }

                return $template;
            }

            return ltrim($handler['app'] . '/' . $handler['controller'] . '/' . $handler['action'], '/');
        }

        return $this->app->param('template', $name);
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