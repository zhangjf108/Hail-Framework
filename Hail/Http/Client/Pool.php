<?php

namespace Hail\Http\Client;

use Hail\Promise\PromisorInterface;
use Psr\Http\Message\RequestInterface;
use Hail\Promise\EachPromise;

/**
 * Sends and iterator of requests concurrently using a capped pool size.
 *
 * The pool will read from an iterator until it is cancelled or until the
 * iterator is consumed. When a request is yielded, the request is sent after
 * applying the "request_options" request options (if provided in the ctor).
 *
 * When a function is yielded by the iterator, the function is provided the
 * "request_options" array that should be merged on top of any existing
 * options, and the function MUST then return a wait-able promise.
 */
class Pool implements PromisorInterface
{
    /** @var EachPromise */
    private $each;

    private $client;
    private $requests;
    private $options;

    /**
     * @param ClientInterface $client   Client used to send the requests.
     * @param iterable        $requests Requests or functions that return
     *                                  requests to send concurrently.
     * @param array           $config   Associative array of options
     *                                  - concurrency: (int) Maximum number of requests to send concurrently
     *                                  - options: Array of request options to apply to each request.
     *                                  - fulfilled: (callable) Function to invoke when a request completes.
     *                                  - rejected: (callable) Function to invoke when a request is rejected.
     */
    public function __construct(
        ClientInterface $client,
        iterable $requests,
        array $config = []
    ) {
        // Backwards compatibility.
        if (isset($config['pool_size'])) {
            $config['concurrency'] = $config['pool_size'];
        } elseif (!isset($config['concurrency'])) {
            $config['concurrency'] = 25;
        }

        $this->client = $client;
        $this->requests = $requests;
        $this->options = [];

        if (isset($config['options'])) {
            $this->options = $config['options'];
            unset($config['options']);
        }

        $this->each = new EachPromise([$this, 'process'], $config);
    }

    public function process()
    {
        foreach ($this->requests as $key => $rfn) {
            if ($rfn instanceof RequestInterface) {
                yield $key => $this->client->sendAsync($rfn, $this->options);
            } elseif (is_callable($rfn)) {
                yield $key => $rfn($this->options);
            } else {
                throw new \InvalidArgumentException('Each value yielded by '
                    . 'the iterator must be a Psr\Http\Message\RequestInterface '
                    . 'or a callable that returns a promise that fulfills '
                    . 'with a Psr\Message\Http\ResponseInterface object.');
            }
        }
    }

    /**
     * @return \Hail\Promise\PromiseInterface
     */
    public function promise()
    {
        return $this->each->promise();
    }

    /**
     * Sends multiple requests concurrently and returns an array of responses
     * and exceptions that uses the same ordering as the provided requests.
     *
     * IMPORTANT: This method keeps every request and response in memory, and
     * as such, is NOT recommended when sending a large number or an
     * indeterminate number of requests concurrently.
     *
     * @param ClientInterface $client   Client used to send the requests
     * @param iterable        $requests Requests to send concurrently.
     * @param array           $options  Passes through the options available in
     *                                  {@see Hail\Http\Client\Pool::__construct}
     *
     * @return array Returns an array containing the response or an exception
     *               in the same order that the requests were sent.
     * @throws \InvalidArgumentException if the event format is incorrect.
     */
    public static function batch(
        ClientInterface $client,
        iterable $requests,
        array $options = []
    ) {
        $res = [];
        self::cmpCallback($options, 'fulfilled', $res);
        self::cmpCallback($options, 'rejected', $res);
        $pool = new static($client, $requests, $options);
        $pool->promise()->wait();
        ksort($res);

        return $res;
    }

    private static function cmpCallback(array &$options, $name, array &$results)
    {
        if (!isset($options[$name])) {
            $options[$name] = function ($v, $k) use (&$results) {
                $results[$k] = $v;
            };
        } else {
            $currentFn = $options[$name];
            $options[$name] = function ($v, $k) use (&$results, $currentFn) {
                $currentFn($v, $k);
                $results[$k] = $v;
            };
        }
    }
}
