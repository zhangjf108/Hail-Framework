<?php

namespace Hail\Http;

use Hail\Util\{
	Arrays, ArrayDot, ArrayTrait
};
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class Input
 *
 * @package Hail\Http
 */
class Input implements \ArrayAccess
{
	use ArrayTrait;

	/** @var ArrayDot */
	protected $items = [];

	/** @var bool */
	protected $all = false;

	/** @var ArrayDot */
	protected $del = [];

	/** @var ServerRequestInterface */
	protected $request;

	/** @var array */
	protected $parsedBody;

	public function __construct(ServerRequestInterface $request)
	{
		$this->items = Arrays::dot();
		$this->del = Arrays::dot();

		$this->request = $request;
	}

	public function setAll(array $array): void
	{
		$this->setMultiple($array);

		$this->all = true;
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 */
	public function set($key, $value = null): void
	{
		if (!$this->all) {
			unset($this->del[$key]);
		}
		$this->items[$key] = $value;
	}

	public function delete($key): void
	{
		if (!$this->all) {
			$this->del[$key] = true;
		}
		unset($this->items[$key]);
	}

	/**
	 * @param string|null $key
	 *
	 * @return mixed
	 */
	public function get(string $key = null)
	{
		if ($key === null) {
			return $this->getAll();
		}

		if ($this->all) {
			return $this->items[$key];
		}

		if (isset($this->del[$key])) {
			return null;
		}

		if (isset($this->items[$key])) {
			return $this->items[$key];
		}

		if ($this->request->getMethod() !== 'GET') {
            $return = Arrays::get($this->request->getParsedBody(), $key);
        }

        $return = $return ?? Arrays::get($this->request->getQueryParams(), $key);

		if ($return !== null) {
			$this->items[$key] = $return;
		} else {
			$this->del[$key] = true;
		}

		return $return;
	}

	public function getAll(): array
	{
		if ($this->all) {
			return $this->items->get();
		}

		$return = array_merge(
            $this->request->getQueryParams(),
            $this->request->getParsedBody()
		);

		if ($this->del !== []) {
			$this->clear(
				$return,
				$this->del->get()
			);
		}

		$this->all = true;

		return $this->items->init($return);
	}

	/**
	 * @param array $array
	 * @param array $del
	 */
	protected function clear(array &$array, array $del): void
	{
		foreach ($del as $k => $v) {
			if (is_array($v) && isset($array[$k])) {
				$this->clear($array[$k], $v);
			} else {
				unset($array[$k]);
			}
		}
	}
}
