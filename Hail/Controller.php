<?php

namespace Hail;

use Hail\Http\ServerRequestWrapper;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class Controller
 *
 * @package Hail
 */
abstract class Controller
{
	use DITrait;

	/**
	 * @var ServerRequestWrapper
	 */
	protected $request;

	final public function __construct(ServerRequestInterface $request)
	{
		$this->request = new ServerRequestWrapper($request);
	}
}