<?php

namespace Hail\Http\Middleware;

use Hail\Http\Middleware\Util\NegotiatorTrait;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\ServerMiddleware\DelegateInterface;

class ContentEncoding implements MiddlewareInterface
{
    use NegotiatorTrait;

    /**
     * @var array Available encodings
     */
    private $encodings = [
        'gzip',
        'deflate',
    ];

    /**
     * Define de available encodings.
     *
     * @param array|null $encodings
     */
    public function __construct(array $encodings = null)
    {
        if ($encodings !== null) {
            $this->encodings = $encodings;
        }
    }

    /**
     * Process a server request and return a response.
     *
     * @param ServerRequestInterface $request
     * @param DelegateInterface      $delegate
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        if ($request->hasHeader('Accept-Encoding')) {
            $accept = $request->getHeaderLine('Accept-Encoding');

            $encoding = $this->getBest($accept, $this->encodings);

            if ($encoding === null) {
                return $delegate->process($request->withoutHeader('Accept-Encoding'));
            }

            return $delegate->process($request->withHeader('Accept-Encoding', $encoding));
        }

        return $delegate->process($request);
    }

    /**
     * @param array $header
     * @param array $priority
     * @param integer      $index
     *
     * @return array|null Headers matched
     */
    protected function match(array $header, array $priority, $index)
    {
        $ac = $header['type'];
        $pc = $priority['type'];

        $equal = !strcasecmp($ac, $pc);

        if ($equal || $ac === '*') {
            $score = 1 * $equal;

            return [
                'quality' => $header['quality'] * $priority['quality'],
                'score' => $score,
                'index' => $index
            ];
        }

        return null;
    }
}