<?php

/**
 * This file is part of the Latte (https://latte.nette.org)
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Hail\Latte;


/**
 * Template loader.
 */
interface LoaderInterface
{
	/**
	 * Returns template source code.
	 */
    public function getContent($name): string;

	/**
	 * Checks whether template is expired.
	 */
    public function isExpired($name, $time): bool;

	/**
	 * Returns referred template name.
	 */
    public function getReferredName($name, $referringName): string;

	/**
	 * Returns unique identifier for caching.
	 */
    public function getUniqueId($name): string;
}
