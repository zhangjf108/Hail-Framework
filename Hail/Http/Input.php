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

	protected $method = 'GET';
	protected $queryParams = [];
	protected $parsedBody = [];
	protected $uploadedFiles = [];

	public function __construct(ServerRequestInterface $request)
	{
		$this->items = Arrays::dot();
		$this->del = Arrays::dot();

		$this->method = $request->getMethod();
		$this->queryParams = $request->getQueryParams();
		$this->parsedBody = $request->getParsedBody();
		$this->uploadedFiles = $request->getUploadedFiles();
	}

	public function setAll(array $array)
	{
		$this->setMultiple($array);

		$this->all = true;
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 */
	public function set($key, $value = null)
	{
		if (!$this->all) {
			unset($this->del[$key]);
		}
		$this->items[$key] = $value;
	}

	public function delete($key)
	{
		if (!$this->all) {
			$this->del[$key] = true;
		}
		unset($this->items[$key]);
	}

	/**
	 * @param string|null $key
	 *
	 * @return array|UploadedFile|mixed|null|string
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

		if ($this->method !== 'GET') {
			$return = $this->uploadedFiles[$key] ??
				$this->parsedBody[$key] ?? null;
		}

		$return = $return ?? $this->queryParams[$key] ?? null;

		if ($return !== null) {
			$this->items[$key] = $return;
		}

		return $return;
	}

	public function getAll()
	{
		if ($this->all) {
			return $this->items->get();
		}

		$return = $this->queryParams;

		if ($this->method !== 'GET') {
			$return = array_merge(
				$return,
				$this->parsedBody,
				$this->uploadedFiles
			);
		}

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
	protected function clear(array &$array, array $del)
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
