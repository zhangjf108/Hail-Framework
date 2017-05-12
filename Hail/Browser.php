<?php

namespace Hail;

use Hail\Http\Client\Client;
use Hail\Http\Client\Pool;
use Hail\Http\Client\RequestOptions;
use Hail\Http\RequestMethod;
use Hail\Promise\PromiseInterface;
use Hail\Util\Json;
use Psr\Http\Message\ResponseInterface;

class Browser
{
    protected $client;
    protected $timeout = 5;
    protected $async = false;

    public function __construct($config = [])
    {
        $this->client = new Client($config);
    }

    /**
     * @return self
     */
    public function async(): self
    {
        $this->async = true;

        return $this;
    }

    /**
     * @param string $url
     * @param array  $params
     * @param array  $headers
     *
     * @return PromiseInterface|ResponseInterface
     */
    public function get(string $url, array $params = [], array $headers = [])
    {
        $options = [
            RequestOptions::HEADERS => $headers,
            RequestOptions::QUERY => $params,
            RequestOptions::TIMEOUT => $this->timeout,
        ];

        return $this->send(RequestMethod::GET, $url, $options);
    }

    /**
     * @param string $url
     * @param array  $params
     * @param array  $headers
     *
     * @return PromiseInterface|ResponseInterface
     */
    public function post(string $url, array $params = [], array $headers = [])
    {
        $options = [
            RequestOptions::HEADERS => $headers,
            RequestOptions::FORM_PARAMS => $params,
            RequestOptions::TIMEOUT => $this->timeout,
        ];

        return $this->send(RequestMethod::POST, $url, $options);
    }

    /**
     * @param string $url
     * @param string $content
     *
     * @return string
     */
    public function socket(string $url, string $content)
    {
        $errno = 0;
        $errstr = '';

        $url = parse_url($url);
        $fp = fsockopen($url['host'], $url['port'], $errno, $errstr, $this->timeout);
        if (!$fp) {
            return Json::encode([
                'ret' => $errno,
                'msg' => $errstr,
            ]);
        }

        fwrite($fp, $content . "\n");
        stream_set_timeout($fp, $this->timeout);
        $return = fgets($fp, 65535);
        fclose($fp);

        return $return;
    }

    /**
     * @param string $url
     * @param array  $params
     * @param array  $headers
     *
     * @return PromiseInterface|ResponseInterface
     */
    public function json(string $url, array $params = [], array $headers = [])
    {
        $options = [
            RequestOptions::HEADERS => $headers,
            RequestOptions::JSON => $params,
            RequestOptions::TIMEOUT => $this->timeout,
        ];

        return $this->send(RequestMethod::POST, $url, $options);
    }

    public function send(string $method, $url, array $options = [])
    {
        $return = $this->async ?
            $this->client->requestAsync($method, $url, $options)
            : $this->client->request($method, $url, $options);

        $this->async = false;

        return $return;
    }

    public function __call($name, $arguments)
    {
        if (method_exists($this->client, $name)) {
            return $this->client->$name(...$arguments);
        }

        throw new \BadMethodCallException('Method not defined in HTTP client:' . $name);
    }

    /**
     * @param int $seconds
     */
    public function timeout(int $seconds)
    {
        $this->timeout = $seconds;
    }

    public function getClient()
    {
        return $this->client;
    }

    public function pool(iterable $requests, array $config = [])
    {
        return new Pool($this->client, $requests, $config);
    }

    public function batch(iterable $requests, array $options = [])
    {
        return Pool::batch($this->client, $requests, $options);
    }
}
