<?php
/**
 * Created by IntelliJ IDEA.
 * User: Hao
 * Date: 2015/12/16 0016
 * Time: 11:21
 */

namespace Hail\Exception;


class ForbiddenRequest extends BadRequest
{
	/** @var int */
	protected $code = 403;
}