<?php

namespace Hail\Filesystem\Client;

use Exception;
use Psr\Http\Message\ResponseInterface;

class BadRequestException extends Exception
{
    public function __construct(ResponseInterface $response)
    {
        $body = json_decode($response->getBody(), true);
        parent::__construct($body['error_summary']);
    }
}