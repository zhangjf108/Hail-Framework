<?php

namespace Hail;

/**
 * Class Action
 *
 * @package Hail
 * @author  Hao Feng <flyinghail@msn.com>
 */
abstract class Action extends Controller
{
	abstract public function __invoke();
}