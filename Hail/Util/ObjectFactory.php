<?php
namespace Hail\Util;

use Hail\Container\Container;

/**
 * Class Model
 *
 * @package Hail
 * @author  Hao Feng <flyinghail@msn.com>
 */
class ObjectFactory implements \ArrayAccess
{
	use ArrayTrait;

	private $namespace;

    /**
     * @var Container
     */
	private $container;

	public function __construct($namespace, Container $container)
	{
		$this->namespace = trim($namespace, '\\') . '\\';
		$this->container = $container;
	}

	public function __call($name, $arguments)
	{
		return $this->$name ?? $this->get($name);
	}

	public function has($key)
	{
		return isset($this->$key);
	}

	public function get($key)
	{
		if (!isset($this->$key)) {
			$class = $this->namespace . ucfirst($key);

			return $this->set($key, $this->container->create($class));
		}

		return $this->$key;
	}

	public function set($key, $value)
	{
		$class = $this->namespace . ucfirst($key);
		if (!$value instanceof $class) {
			throw new \LogicException("Object Not Instance of $class");
		}

		return $this->$key = $value;
	}

	public function delete($key)
	{
		if (isset($this->$key)) {
			unset($this->$key);
		}
	}
}