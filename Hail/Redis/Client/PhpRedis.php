<?php
/**
 * @author    Colin Mollenhour <colin@mollenhour.com>
 * @copyright 2011 Colin Mollenhour <colin@mollenhour.com>
 * @license   http://www.opensource.org/licenses/mit-license.php The MIT License
 * @package   static
 */
namespace Hail\Redis\Client;

use Hail\Redis\Exception\RedisException;

/**
 * Class PhpRedis
 *
 * @package Hail\Redis\Client
 * @author  Hao Feng <flyinghail@msn.com>
 * @inheritdoc
 */
class PhpRedis extends AbstractClient
{
	/**
	 * Redis instance in multi-mode
	 *
	 * @var \Redis
	 */
	protected $redisMulti;

	/**
	 * @var int
	 */
	protected $requests = 0;

	protected static $typeMap = [
		\Redis::REDIS_NOT_FOUND => 'none',
		\Redis::REDIS_STRING => 'string',
		\Redis::REDIS_SET => 'set',
		\Redis::REDIS_LIST => 'list',
		\Redis::REDIS_ZSET => 'zset',
		\Redis::REDIS_HASH => 'hash',
	];

	protected static $skipMap = [
		'get' => true,
		'set' => true,
		'hget' => true,
		'hset' => true,
		'setex' => true,
		'mset' => true,
		'msetnx' => true,
		'hmset' => true,
		'hmget' => true,
		'del' => true,
		'zrangebyscore' => true,
		'zrevrangebyscore' => true,
		'zrange' => true,
		'zrevrange' => true,
		'subscribe' => true,
		'psubscribe' => true,
		'scan' => true,
		'sscan' => true,
		'hscan' => true,
		'zscan' => true,
	];

	/**
	 * Aliases for backwards compatibility with phpredis
	 *
	 * @var array
	 */
	protected static $wrapperMethods = ['delete' => 'del', 'getkeys' => 'keys', 'sremove' => 'srem'];

	/**
	 * @throws RedisException
	 */
	protected function connect()
	{
		if (!$this->redis) {
			$this->redis = new \Redis();
		}

		$result = $this->persistent
			? $this->redis->pconnect($this->host, $this->port, $this->timeout ?: 0.0, $this->persistent)
			: $this->redis->connect($this->host, $this->port, $this->timeout ?: 0.0);

		// Use recursion for connection retries
		if (!$result) {
			$this->connectFailures++;
			if ($this->connectFailures <= $this->maxConnectRetries) {
				$this->connect();

				return;
			}
			$failures = $this->connectFailures;
			$this->connectFailures = 0;
			throw new RedisException("Connection to Redis {$this->host}:{$this->port} failed after $failures failures.");
		}

		$this->connectFailures = 0;
		$this->connected = true;

		$this->redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);

		// Set read timeout
		if ($this->readTimeout !== null) {
			$this->setReadTimeout($this->readTimeout);
		}

		if ($password = $this->getPassword()) {
			$this->auth($password);
		}

		if ($this->database !== 0) {
			$this->select($this->database);
		}
	}

	/**
	 * Set the read timeout for the connection. Use 0 to disable timeouts entirely (or use a very long timeout
	 * if not supported).
	 *
	 * @param int $timeout 0 (or -1) for no timeout, otherwise number of seconds
	 *
	 * @throws RedisException
	 * @return static
	 */
	public function setReadTimeout(int $timeout)
	{
		if ($timeout < -1) {
			throw new RedisException('Timeout values less than -1 are not accepted.');
		}
		$this->readTimeout = $timeout;
		if ($this->connected) {
			// supported in phpredis 2.2.3
			// a timeout value of -1 means reads will not timeout
			$timeout = $timeout === 0 ? -1 : $timeout;
			$this->redis->setOption(\Redis::OPT_READ_TIMEOUT, $timeout);
		}

		return $this;
	}

	/**
	 * @return bool
	 */
	public function close()
	{
		$result = true;
		if ($this->connected && !$this->persistent) {
			try {
				$this->redis->close();
				$this->connected = false;
			} catch (\Exception $e) {
				// Ignore exceptions on close
			}
		}

		return $result;
	}

	public function __call($name, $args)
	{
		$name = strtolower($name);

		// Use aliases to be compatible with phpredis wrapper
		$name = self::$wrapperMethods[$name] ?? $name;

		// Tweak arguments
		if (!isset(self::$skipMap[$name])) {
			switch ($name) {
				case 'zunionstore':
					$cArgs = [];
					$cArgs[] = array_shift($args); // destination
					$cArgs[] = array_shift($args); // keys
					if (isset($args[0], $args[0]['weights'])) {
						$cArgs[] = (array) $args[0]['weights'];
					} else {
						$cArgs[] = null;
					}
					if (isset($args[0], $args[0]['aggregate'])) {
						$cArgs[] = strtoupper($args[0]['aggregate']);
					}
					$args = $cArgs;
					break;
				case 'mget':
					if (isset($args[0]) && !is_array($args[0])) {
						$args = [$args];
					}
					break;
				case 'lrem':
					$args = [$args[0], $args[2], $args[1]];
					break;
				case 'eval':
				case 'evalsha':
					$cKeys = $cArgs = [];
					if (isset($args[1])) {
						if (is_array($args[1])) {
							$cKeys = $args[1];
						} elseif (is_string($args[1])) {
							$cKeys = [$args[1]];
						}
					}
					if (isset($args[2])) {
						if (is_array($args[2])) {
							$cArgs = $args[2];
						} elseif (is_string($args[2])) {
							$cArgs = [$args[2]];
						}
					}
					$args = [$args[0], array_merge($cKeys, $cArgs), count($cKeys)];
					break;
				case 'pipeline':
				case 'multi':
					if ($this->redisMulti !== null) {
						return $this;
					}
					break;
				default:
					// Flatten arguments
					$args = self::flattenArguments($args);
			}
		}

		try {
			// Send request, retry one time when using persistent connections on the first request only
			if ($this->persistent && $this->requests === 0) {
				$this->requests = 1;
				try {
					$response = $this->redis->$name(...$args);
				} catch (\RedisException $e) {
					if ($e->getMessage() === 'read error on connection') {
						$this->connected = false;
						$this->connect();
						$response = $this->redis->$name(...$args);
					} else {
						throw $e;
					}
				}
			} else {
				$response = $this->redis->$name(...$args);
			}

			// Proxy pipeline mode to the phpredis library
			if ($name === 'pipeline' || $name === 'multi') {
				$this->redisMulti = $response;

				return $this;
			}

			if ($name === 'exec' || $name === 'discard') {
				$this->redisMulti = null;

				return $response;
			}

			if ($this->redisMulti !== null) { // Multi and pipeline return self for chaining
				return $this;
			}
		} catch (\RedisException $e) {
			$code = 0;
			if (!$this->redis->isConnected()) {
				$this->connected = false;
				$code = RedisException::CODE_DISCONNECTED;
			}
			throw new RedisException($e->getMessage(), $code, $e);
		}

		#echo "> $name : ".substr(print_r($response, TRUE),0,100)."\n";

		// change return values where it is too difficult to minim in standalone mode
		switch ($name) {
			case 'hmget':
				$response = array_values($response);
				break;

			case 'type':
				$response = self::$typeMap[$response];
				break;

			// Handle scripting errors
			case 'eval':
			case 'evalsha':
			case 'script':
				$error = $this->redis->getLastError();
				$this->redis->clearLastError();
				if ($error && strpos($error, 'NOSCRIPT') === 0) {
					$response = null;
				} else if ($error) {
					throw new RedisException($error);
				}
				break;
			default:
				$error = $this->redis->getLastError();
				$this->redis->clearLastError();
				if ($error) {
					throw new RedisException($error);
				}
				break;
		}

		return $response;
	}
}
