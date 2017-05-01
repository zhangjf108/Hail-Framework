<?php

/**
 * This file is part of the Latte (https://latte.nette.org)
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Hail\Latte\Macros;

use Hail\Latte\{
	MacroInterface, Strict
};
use Hail\Latte\Compiler\{
	Compiler, MacroNode, PhpWriter
};
use Hail\Latte\Runtime\Filters;
use Hail\Latte\Exception\CompileException;


/**
 * Base IMacro implementation. Allows add multiple macros.
 */
class MacroInterfaceSet implements MacroInterface
{
	use Strict;

	/** @var Compiler */
	private $compiler;

	/** @var array */
	private $macros;


	public function __construct(Compiler $compiler)
	{
		$this->compiler = $compiler;
	}


	public function addMacro($name, $begin, $end = NULL, $attr = NULL, $flags = NULL)
	{
		if (!$begin && !$end && !$attr) {
			throw new \InvalidArgumentException("At least one argument must be specified for macro '$name'.");
		}

		$macro = [$begin, $end, $attr];
		foreach ([$begin, $end, $attr] as &$arg) {
			if ($arg && !is_string($arg)) {
				$arg = \Closure::fromCallable($arg);
			}
		}
		unset($arg);

		$this->macros[$name] = $macro;
		$this->compiler->addMacro($name, $this, $flags);
		return $this;
	}


	/**
	 * Initializes before template parsing.
	 * @return void
	 */
	public function initialize()
	{
	}


	/**
	 * Finishes template parsing.
	 * @return array|NULL [prolog, epilog]
	 */
	public function finalize()
	{
	}


	/**
	 * New node is found.
	 * @return bool|NULL
	 */
	public function nodeOpened(MacroNode $node)
	{
		list($begin, $end, $attr) = $this->macros[$node->name];
		$node->empty = !$end;

		if ($node->modifiers
			&& (!$begin || (is_string($begin) && strpos($begin, '%modify') === FALSE))
			&& (!$end || (is_string($end) && strpos($end, '%modify') === FALSE))
			&& (!$attr || (is_string($attr) && strpos($attr, '%modify') === FALSE))
		) {
			throw new CompileException('Modifiers are not allowed in ' . $node->getNotation());
		}

		if ($node->args
			&& (!$begin || (is_string($begin) && strpos($begin, '%node') === FALSE))
			&& (!$end || (is_string($end) && strpos($end, '%node') === FALSE))
			&& (!$attr || (is_string($attr) && strpos($attr, '%node') === FALSE))
		) {
			throw new CompileException('Arguments are not allowed in ' . $node->getNotation());
		}

		if ($attr && $node->prefix === $node::PREFIX_NONE) {
			$node->empty = TRUE;
			$node->context[1] = Compiler::CONTEXT_HTML_ATTRIBUTE;
			$res = $this->compile($node, $attr);
			if ($res === FALSE) {
				return FALSE;
			} elseif (!$node->attrCode) {
				$node->attrCode = "<?php $res ?>";
			}
			$node->context[1] = Compiler::CONTEXT_HTML_TEXT;

		} elseif ($begin) {
			$res = $this->compile($node, $begin);
			if ($res === FALSE || ($node->empty && $node->prefix)) {
				return FALSE;
			} elseif (!$node->openingCode && is_string($res) && $res !== '') {
				$node->openingCode = "<?php $res ?>";
			}

		} elseif (!$end) {
			return FALSE;
		}
	}


	/**
	 * Node is closed.
	 * @return void
	 */
	public function nodeClosed(MacroNode $node)
	{
		if (isset($this->macros[$node->name][1])) {
			$res = $this->compile($node, $this->macros[$node->name][1]);
			if (!$node->closingCode && is_string($res) && $res !== '') {
				$node->closingCode = "<?php $res ?>";
			}
		}
	}


	/**
	 * Generates code.
	 * @return string|bool|NULL
	 */
	private function compile(MacroNode $node, $def)
	{
		$node->tokenizer->reset();
		$writer = PhpWriter::using($node);
		return is_string($def)
			? $writer->write($def)
			: $def($node, $writer);
	}


	public function getCompiler(): Compiler
	{
		return $this->compiler;
	}


	/** @internal */
	protected function checkExtraArgs(MacroNode $node)
	{
		if ($node->tokenizer->isNext()) {
			$args = Filters::truncate($node->tokenizer->joinAll(), 20);
			trigger_error("Unexpected arguments '$args' in " . $node->getNotation());
		}
	}

}
