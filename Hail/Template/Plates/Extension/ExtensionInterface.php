<?php

namespace Hail\Template\Plates\Extension;

use Hail\Template\Plates\Engine;

/**
 * A common interface for extensions.
 */
interface ExtensionInterface
{
    public function register(Engine $engine);
}
