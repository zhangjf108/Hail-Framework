<?php

namespace Hail;

use Psr\Http\Message\ResponseInterface;

interface TemplateInterface
{
    public function renderToResponse(ResponseInterface $response, string $name, array $params = []);
}