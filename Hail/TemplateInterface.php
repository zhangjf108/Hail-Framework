<?php

namespace Hail;

use Psr\Http\Message\ResponseInterface;

interface TemplateInterface
{
    public function render(ResponseInterface $response, string $name, array $params = []);
}