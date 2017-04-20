<?php

declare(strict_types=1);

namespace Hail\Http;

use Psr\Http\Message\{
	ServerRequestInterface, StreamInterface, UploadedFileInterface
};

/**
 * Class ServerRequestWrapper
 *
 * @package Hail\Http
 *
 * @author  Hao Feng <flyinghail@msn.com>
 *
 * @method string getProtocolVersion()
 * @method array getHeaders()
 * @method array getHeader(string $name)
 * @method string getHeaderLine(string $name)
 * @method StreamInterface getBody()
 * @method bool hasHeader(string $name)
 * @method string getRequestTarget()
 * @method string getMethod()
 * @method string getUri()
 * @method array getServerParams()
 * @method array getCookieParams()
 * @method array getQueryParams()
 * @method array getUploadedFiles()
 * @method array getParsedBody()
 * @method array getAttributes()
 * @method mixed getAttribute(string $name, mixed $default = null)
 */
class ServerRequestWrapper
{
	/**
	 * @var ServerRequestInterface
	 */
	protected $serverRequest;

	/**
	 * @var Input
	 */
	public $input;

	/**
	 * ServerRequestWrapper constructor.
	 *
	 * @param ServerRequestInterface $serverRequest
	 */
	public function __construct(ServerRequestInterface $serverRequest)
	{
		$this->serverRequest = $serverRequest;
		$this->input = new Input($serverRequest);
	}

	public function __call($name, $arguments)
	{
		if (strpos($name, 'with') === 0) {
			throw new \BadMethodCallException('Wrapper not support use this method: ' . $name);
		}

		return $this->serverRequest->$name(...$arguments);
	}

	/**
	 * @param array|null $values
	 *
	 * @return array
	 */
	public function inputs(array $values = null): array
	{
		if ($values === null) {
			return $this->input->getAll();
		}

		$this->input->setAll($values);

		return $values;
	}

	/**
	 * @param string $name
	 * @param mixed  $value
	 *
	 * @return mixed
	 */
	public function input(string $name, $value = null)
	{
		if ($value === null) {
			return $this->input->get($name);
		}

		$this->input->set($name, $value);

		return $value;
	}

	/**
	 * @return array
	 */
	public function files(): array
	{
		return $this->serverRequest->getUploadedFiles();
	}

	/**
	 * @param string $name
	 *
	 * @return null|UploadedFileInterface
	 */
	public function file(string $name): ?UploadedFileInterface
	{
		return $this->serverRequest->getUploadedFiles()[$name] ?? null;
	}

	/**
	 * @param string $name
	 *
	 * @return null|string
	 */
	public function cookie(string $name): ?string
	{
		return $this->getCookieParams()[$name] ?? null;
	}
}
