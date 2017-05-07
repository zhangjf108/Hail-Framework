<?php

namespace Hail;

use Hail\Http\Client\Client;
use Hail\Util\Json;
use Psr\Http\Message\ResponseInterface;

class Browser
{
    protected $client;
    protected $timeout = 10;
    
    public function __construct($config = [])
    {
        $this->client = new Client($config);
    }

    /**
	 * @param string $url
	 * @param array $params
	 * @param array $headers
	 *
	 * @return ResponseInterface
	 */
	public function get(string $url, array $params = [], array $headers = [])
	{
		return $this->client->get($url, [
		    'headers' => $headers,
            'query' => $params,
            'timeout' => $this->timeout
        ]);
	}

	/**
	 * @param string $url
	 * @param array $params
	 * @param array $headers
	 *
	 * @return ResponseInterface
	 */
	public function post(string $url, array $params = [], array $headers = [])
	{
		return $this->client->post($url, [
		    'headers' => $headers,
            'form_params' => $params,
            'timeout' => $this->timeout
        ]);
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
		$fp = fsockopen($url['host'], $url['port'], $errno, $errstr, 3);
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
	 * @param array $params
	 * @param array $headers
	 *
	 * @return ResponseInterface
	 */
	public function json(string $url, array $params = [], array $headers = [])
	{
        return $this->client->post($url, [
            'headers' => $headers,
            'json' => $params,
            'timeout' => $this->timeout
        ]);
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
}
