<?php

/**
 * This file is part of the Latte (https://latte.nette.org)
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Hail\Latte\Runtime;


interface HtmlStringInterface
{

	/**
	 * @return string in HTML format
	 */
	public function __toString(): string;
}
