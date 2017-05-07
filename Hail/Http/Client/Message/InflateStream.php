<?php

namespace Hail\Http\Client\Message;

use Hail\Http\Factory;
use Hail\Http\Helpers;
use Hail\Http\Message\Stream;
use Psr\Http\Message\StreamInterface;

/**
 * Uses PHP's zlib.inflate filter to inflate deflate or gzipped content.
 *
 * This stream decorator skips the first 10 bytes of the given stream to remove
 * the gzip header, converts the provided stream to a PHP stream resource,
 * then appends the zlib.inflate filter. The stream is then converted back
 * to a Guzzle stream resource to be used as a Guzzle stream.
 *
 * @link http://tools.ietf.org/html/rfc1952
 * @link http://php.net/manual/en/filters.compression.php
 */
class InflateStream extends Stream
{
    public function __construct(StreamInterface $stream)
    {
        // read the first 10 bytes, ie. gzip header
        $header = $stream->read(10);

        if (substr(bin2hex($header), 6, 2) === '08') {
            // we have a filename, read until nil
            $end = chr(0);
            while ($stream->read(1) !== $end) {
                ;
            }
        }

        $new = Factory::stream();
        Helpers::copyToStream($stream, $new);

        $resource = $new->detach();
        stream_filter_append($resource, 'zlib.inflate', STREAM_FILTER_READ);

        parent::__construct($resource);
    }
}
