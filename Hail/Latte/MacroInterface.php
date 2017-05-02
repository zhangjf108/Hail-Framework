<?php

/**
 * This file is part of the Latte (https://latte.nette.org)
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Hail\Latte;

use Hail\Latte\Compiler\MacroNode;

/**
 * Latte macro.
 */
interface MacroInterface
{
	const
		AUTO_EMPTY = 4,
		AUTO_CLOSE = 64,
		ALLOWED_IN_HEAD = 128,
		DEFAULT_FLAGS = 0;

	/**
	 * Initializes before template parsing.
	 * @return void
	 */
	public function initialize(): void;

	/**
	 * Finishes template parsing.
	 * @return array|NULL [prolog, epilog]
	 */
    public function finalize(): ?array;

	/**
	 * New node is found. Returns FALSE to reject.
	 * @return bool|NULL
	 */
    public function nodeOpened(MacroNode $node): ?bool;

	/**
	 * Node is closed.
	 * @return void
	 */
    public function nodeClosed(MacroNode $node): void;
}
