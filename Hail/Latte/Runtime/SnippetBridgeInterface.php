<?php

/**
 * This file is part of the Latte (https://latte.nette.org)
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Hail\Latte\Runtime;


/**
 * Snippet bridge
 * @internal
 */
interface SnippetBridgeInterface
{
    public function isSnippetMode(): bool;

    public function setSnippetMode(bool $snippetMode);

    public function needsRedraw(string $name): bool;

    /**
     * @return void
     */
    public function markRedrawn(string $name);

    public function getHtmlId(string $name): string;

    /**
     * @return void
     */
    public function addSnippet(string $name, string $content);

    /**
     * @return void
     */
    public function renderChildren();
}
