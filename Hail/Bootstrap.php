<?php
namespace Hail;

/**
 * Class Bootstrap
 * @package Hail
 */
class Bootstrap
{
	private static $alias = [
		'db' => 'DB',
		'cdb' => 'CachedDB',
		'app' => 'Application',
		'lib' => 'Library',
	];

	private static $proxies = [];

	public static function di($start = [])
	{
		require HAIL_PATH . 'Cache/EmbeddedTrait.php';
		require HAIL_PATH . 'DI.php';

		$set = [
			'embedded' => function ($c) {
				require HAIL_PATH . 'Cache/Embedded.php';
				return new Cache\Embedded(
					EMBEDDED_CACHE_ENGINE
				);
			},

			'config' => function ($c) {
				require HAIL_PATH . 'Config.php';
				return new Config($c);
			},

			'loader' => function ($c) {
				require HAIL_PATH . 'Loader/PSR4.php';
				$loader = new Loader\PSR4($c);
				$loader->addPrefixes(
					$c['config']->get('env.autoload')
				);
				return $loader;
			},

			'alias' => function ($c) {
				return new Loader\Alias(
					self::alias($c)
				);
			},
		];

		$optional = self::diOptional();
		if (empty($start)) {
			$set = array_merge($set, $optional);
		} else {
			$set = [];
			foreach ($start as $v) {
				if (isset($optional[$v])) {
					$set[$v] = $optional[$v];
				}
			}
		}

		$di = new DI($set);

		$di['loader']->register();
		$di['alias']->register();
		\DI::swap($di);

		return $di;
	}

	private static function alias($di)
	{
		$keys = $di->keys();
		$alias = $di['config']->get('env.alias');
		$alias['DI'] = 'Hail\\Facades\\DI';
		foreach ($keys as $v) {
			$name = self::$alias[$v] ?? ucfirst($v);
			$alias[$name] = 'Hail\\Facades\\' . $name;
		}
		return $alias;
	}

	public static function diOptional()
	{
		return [
			'gettext' => function ($c) {
				return new I18N\Gettext();
			},

			'cache' => function ($c) {
				return new Cache(
					$c['config']->get('app.cache')
				);
			},

			'db' => function ($c) {
				return new DB\Medoo(
					$c['config']->get('app.database')
				);
			},

			'cdb' => function ($c) {
				return new DB\Cache();
			},

			'session' => function ($c) {
				return new Session(
					$c['config']->get('app.session'),
					$c['config']->get('app.cookie')
				);
			},

			'router' => function ($c) {
				return new Router($c);
			},

			'request' => function ($c) {
				return Bootstrap::httpRequest();
			},

			'response' => function ($c) {
				return new Http\Response(
					$c['config']->get('app.response')
				);
			},

			'cookie' => function ($c) {
				return new Cookie(
					$c['config']->get('app.cookie')
				);
			},

			'event' => function ($c) {
				return new Event\Emitter();
			},

			'app' => function ($c) {
				return new Application();
			},

			'output' => function ($c) {
				return new Output();
			},

			'acl' => function ($c) {
				return new Acl();
			},

			'model' => function ($c) {
				return new Utils\ObjectFactory('Model');
			},

			'lib' => function ($c) {
				return new Utils\ObjectFactory('Library');
			},

			'template' => function ($c) {
				return new Latte\Engine(
					$c['config']->get('app.template')
				);
			},

			'client' => function ($c) {
				return new Browser();
			},
		];
	}

	public static function httpRequest()
	{
		$url = new Http\UrlScript();

		$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null;
		if ($method === 'POST' && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])
			&& preg_match('#^[A-Z]+\z#', $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])
		) {
			$method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
		}

		$remoteAddr = !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : NULL;
		$remoteHost = !empty($_SERVER['REMOTE_HOST']) ? $_SERVER['REMOTE_HOST'] : NULL;

		// use real client address and host if trusted proxy is used
		$usingTrustedProxy = $remoteAddr &&
			array_filter(self::$proxies,
				function ($proxy) use ($remoteAddr) {
					return Http\Helpers::ipMatch($remoteAddr, $proxy);
				}
			);
		if ($usingTrustedProxy) {
			if (!empty($_SERVER['HTTP_FORWARDED'])) {
				$forwardParams = preg_split('/[,;]/', $_SERVER['HTTP_FORWARDED']);
				foreach ($forwardParams as $forwardParam) {
					list($key, $value) = explode('=', $forwardParam, 2) + [1 => NULL];
					$proxyParams[strtolower(trim($key))][] = trim($value, " \t\"");
				}

				if (isset($proxyParams['for'])) {
					$address = $proxyParams['for'][0];
					if (strpos($address, '[') === FALSE) { //IPv4
						$remoteAddr = explode(':', $address)[0];
					} else { //IPv6
						$remoteAddr = substr($address, 1, strpos($address, ']') - 1);
					}
				}

				if (isset($proxyParams['host']) && count($proxyParams['host']) === 1) {
					$host = $proxyParams['host'][0];
					$startingDelimiterPosition = strpos($host, '[');
					if ($startingDelimiterPosition === FALSE) { //IPv4
						$remoteHostArr = explode(':', $host);
						$remoteHost = $remoteHostArr[0];
						if (isset($remoteHostArr[1])) {
							$url->setPort((int) $remoteHostArr[1]);
						}
					} else { //IPv6
						$endingDelimiterPosition = strpos($host, ']');
						$remoteHost = substr($host, strpos($host, '[') + 1, $endingDelimiterPosition - 1);
						$remoteHostArr = explode(':', substr($host, $endingDelimiterPosition));
						if (isset($remoteHostArr[1])) {
							$url->setPort((int) $remoteHostArr[1]);
						}
					}
				}

				$scheme = (isset($proxyParams['scheme']) && count($proxyParams['scheme']) === 1) ? $proxyParams['scheme'][0] : 'http';
				$url->setScheme(strcasecmp($scheme, 'https') === 0 ? 'https' : 'http');
			} else {
				if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
					$url->setScheme(strcasecmp($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') === 0 ? 'https' : 'http');
				}

				if (!empty($_SERVER['HTTP_X_FORWARDED_PORT'])) {
					$url->setPort((int) $_SERVER['HTTP_X_FORWARDED_PORT']);
				}

				if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
					$xForwardedForWithoutProxies = array_filter(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']), function ($ip) {
						return !array_filter(self::$proxies, function ($proxy) use ($ip) {
							return Http\Helpers::ipMatch(trim($ip), $proxy);
						});
					});
					$remoteAddr = trim(end($xForwardedForWithoutProxies));
					$xForwardedForRealIpKey = key($xForwardedForWithoutProxies);
				}

				if (isset($xForwardedForRealIpKey) && !empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
					$xForwardedHost = explode(',', $_SERVER['HTTP_X_FORWARDED_HOST']);
					if (isset($xForwardedHost[$xForwardedForRealIpKey])) {
						$remoteHost = trim($xForwardedHost[$xForwardedForRealIpKey]);
					}
				}
			}
		}

		return new Http\Request($url, $method, $remoteAddr, $remoteHost);
	}
}