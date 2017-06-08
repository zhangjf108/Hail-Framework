<?php
namespace Hail\Http\Client\Middleware;

use Hail\Util\MimeType;
use Hail\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Prepares requests that contain a body, adding the Content-Length,
 * Content-Type, and Expect headers.
 */
class PrepareBody implements MiddlewareInterface
{
    /** @var callable  */
    private $nextHandler;

    /**
     * @param callable $nextHandler Next handler to invoke.
     */
    public function __construct(callable $nextHandler)
    {
        $this->nextHandler = $nextHandler;
    }

    /**
     * @param RequestInterface $request
     * @param array            $options
     *
     * @return PromiseInterface
     */
    public function __invoke(RequestInterface $request, array $options)
    {
        $fn = $this->nextHandler;

        // Don't do anything if the request has no body.
        $body = $request->getBody();
        $size = $body->getSize();
        if ($size === 0) {
            return $fn($request, $options);
        }

        // Add a default content-type if possible.
        if (!$request->hasHeader('Content-Type') &&
            ($uri = $body->getMetadata('uri')) &&
            ($type = MimeType::getMimeType($uri))
        ) {
            $request = $request->withHeader('Content-Type', $type);
        }

        // Add a default content-length or transfer-encoding header.
        if (!$request->hasHeader('Content-Length')
            && !$request->hasHeader('Transfer-Encoding')
        ) {
            if ($size !== null) {
                $request = $request->withHeader('Content-Length', $size);
            } else {
                $request = $request->withHeader('Transfer-Encoding', 'chunked');
            }
        }

        // Add the expect header if needed.
        $request = $this->addExpectHeader($request, $options);

        return $fn($request, $options);
    }

    private function addExpectHeader(
        RequestInterface $request,
        array $options
    ) {
        // Determine if the Expect header should be used
        if ($request->hasHeader('Expect')) {
            return $request;
        }

        $expect = $options['expect'] ?? null;

        // Return if disabled or if you're not using HTTP/1.1 or HTTP/2.0
        if ($expect === false || $request->getProtocolVersion() < 1.1) {
            return $request;
        }

        // The expect header is unconditionally enabled
        if ($expect === true) {
            return $request->withHeader('Expect', '100-Continue');
        }

        // By default, send the expect header when the payload is > 1mb
        if ($expect === null) {
            $expect = 1048576;
        }

        // Always add if the body cannot be rewound, the size cannot be
        // determined, or the size is greater than the cutoff threshold
        $body = $request->getBody();
        $size = $body->getSize();

        if ($size === null || $size >= (int) $expect || !$body->isSeekable()) {
            return $request->withHeader('Expect', '100-Continue');
        }

        return $request;
    }
}
