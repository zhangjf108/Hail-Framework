<?php

namespace Hail\Http;

use Psr\Http\{
	ServerMiddleware\DelegateInterface,
	Message\ResponseInterface,
	Message\ServerRequestInterface
};

/**
 * PSR-15 delegate wrapper
 *
 */
class Delegate implements DelegateInterface
{
	/**
	 * @var Dispatcher
	 */
	private $dispatcher;

	/**
	 * @var null|DelegateInterface
	 */
	private $delegate;

	/**
	 * @param Dispatcher             $dispatcher
	 * @param DelegateInterface|null $delegate
	 */
	public function __construct(Dispatcher $dispatcher, DelegateInterface $delegate = null)
	{
		$this->dispatcher = $dispatcher;
		$this->delegate = $delegate;
	}

	/**
	 * {@inheritdoc}
	 */
	public function process(ServerRequestInterface $request): ResponseInterface
	{
		$middleware = $this->dispatcher->next();
		if ($middleware === null) {
			if ($this->delegate !== null) {
				return $this->delegate->process($request);
			}

			throw new \LogicException('Middleware queue exhausted, with no response returned.');
		}

		return $middleware->process($request, $this);
	}
}
