<?php

namespace Hail\Template;

use Hail\Util\Arrays;

final class Template
{
    private $params;

    public function __construct()
    {
        $this->params = Arrays::dot();
    }

    public function param($name)
    {
        return $this->params[$name] ?? null;
    }

    public function render($file, array $params)
    {
        $this->params->replace($params);

        include $file;
    }
}