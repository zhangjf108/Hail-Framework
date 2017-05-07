<?php

namespace Hail\Http\Client;

use Hail\Http\Client\Handler\CurlHandler;
use Hail\Http\Client\Handler\CurlMultiHandler;
use Hail\Http\Client\Handler\Proxy;
use Hail\Http\Client\Handler\StreamHandler;
use Hail\Http\Factory;
use Hail\Http\Helpers as HttpHelpers;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

class Helpers
{
    /**
     * Debug function used to describe the provided value type and class.
     *
     * @param mixed $input
     *
     * @return string Returns a string containing the type of the variable and
     *                if a class is provided, the class name.
     */
    public static function describeType($input)
    {
        switch (gettype($input)) {
            case 'object':
                return 'object(' . get_class($input) . ')';
            case 'array':
                return 'array(' . count($input) . ')';
            default:
                ob_start();
                var_dump($input);

                // normalize float vs double
                return str_replace('double(', 'float(', rtrim(ob_get_clean()));
        }
    }

    /**
     * Parses an array of header lines into an associative array of headers.
     *
     * @param array $lines Header lines array of strings in the following
     *                     format: "Name: Value"
     *
     * @return array
     */
    public static function headersFromLines($lines)
    {
        $headers = [];

        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);
            $headers[trim($parts[0])][] = isset($parts[1])
                ? trim($parts[1])
                : null;
        }

        return $headers;
    }

    /**
     * Returns a debug stream based on the provided variable.
     *
     * @param mixed $value Optional value
     *
     * @return resource
     */
    public static function debugResource($value = null)
    {
        if (is_resource($value)) {
            return $value;
        }

        if (defined('STDOUT')) {
            return STDOUT;
        }

        return fopen('php://output', 'wb');
    }

    /**
     * Chooses and creates a default handler to use based on the environment.
     *
     * The returned handler is not wrapped by any default middlewares.
     *
     * @throws \RuntimeException if no viable Handler is available.
     * @return callable Returns the best handler for the given system.
     */
    public static function chooseHandler()
    {
        $handler = Proxy::wrapSync(
            new CurlMultiHandler(),
            new CurlHandler()
        );

        if (ini_get('allow_url_fopen')) {
            $handler = $handler
                ? Proxy::wrapStreaming($handler, new StreamHandler())
                : new StreamHandler();
        } elseif (!$handler) {
            throw new \RuntimeException('Hail\Http\Client requires cURL, the '
                . 'allow_url_fopen ini setting, or a custom HTTP handler.');
        }

        return $handler;
    }

    /**
     * Get the default User-Agent string
     *
     * @return string
     */
    public static function defaultUserAgent()
    {
        static $defaultAgent = '';

        if (!$defaultAgent) {
            $defaultAgent = 'HAIL-HTTP-Client/' . Client::VERSION;
            if (extension_loaded('curl') && function_exists('curl_version')) {
                $defaultAgent .= ' curl/' . \curl_version()['version'];
            }
            $defaultAgent .= ' PHP/' . PHP_VERSION;
        }

        return $defaultAgent;
    }

    /**
     * Creates an associative array of lowercase header names to the actual
     * header casing.
     *
     * @param array $headers
     *
     * @return array
     */
    public static function normalizeHeaderKeys(array $headers)
    {
        $result = [];
        foreach (array_keys($headers) as $key) {
            $result[strtolower($key)] = $key;
        }

        return $result;
    }

    /**
     * Returns true if the provided host matches any of the no proxy areas.
     *
     * This method will strip a port from the host if it is present. Each pattern
     * can be matched with an exact match (e.g., "foo.com" == "foo.com") or a
     * partial match: (e.g., "foo.com" == "baz.foo.com" and ".foo.com" ==
     * "baz.foo.com", but ".foo.com" != "foo.com").
     *
     * Areas are matched in the following cases:
     * 1. "*" (without quotes) always matches any hosts.
     * 2. An exact match.
     * 3. The area starts with "." and the area is the last part of the host. e.g.
     *    '.mit.edu' will match any host that ends with '.mit.edu'.
     *
     * @param string $host         Host to check against the patterns.
     * @param array  $noProxyArray An array of host patterns.
     *
     * @return bool
     */
    public static function isHostInNoproxy($host, array $noProxyArray)
    {
        if ($host === '') {
            throw new \InvalidArgumentException('Empty host provided');
        }

        // Strip port if present.
        if (strpos($host, ':')) {
            $host = explode($host, ':', 2)[0];
        }

        foreach ($noProxyArray as $area) {
            // Always match on wildcards.
            if ($area === '*') {
                return true;
            }

            if (empty($area)) {
                // Don't match on empty values.
                continue;
            }

            if ($area === $host) {
                // Exact matches.
                return true;
            }

            // Special match if the area when prefixed with ".". Remove any
            // existing leading "." and add a new leading ".".
            $area = '.' . ltrim($area, '.');
            if (substr($host, -(strlen($area))) === $area) {
                return true;
            }
        }

        return false;
    }

    /**
     * Removes dot segments from a path and returns the new path.
     *
     * @param string $path
     *
     * @return string
     * @link http://tools.ietf.org/html/rfc3986#section-5.2.4
     */
    public static function removeDotSegments($path)
    {
        if ($path === '' || $path === '/') {
            return $path;
        }

        $results = [];
        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '..') {
                array_pop($results);
            } elseif ($segment !== '' && $segment !== '.') {
                $results[] = $segment;
            }
        }

        $newPath = implode('/', $results);

        if ($path[0] === '/' && (!isset($newPath[0]) || $newPath[0] !== '/')) {
            // Re-add the leading slash if necessary for cases like "/.."
            $newPath = '/' . $newPath;
        }

        if ($newPath !== '' && ($segment === '.' || $segment === '..' || $segment === '')) {
            // Add the trailing slash if necessary
            // If newPath is not empty, then $segment must be set and is the last segment from the foreach
            $newPath .= '/';
        }

        return $newPath;
    }

    /**
     * Converts the relative URI into a new URI that is resolved against the base URI.
     *
     * @param UriInterface $base Base URI
     * @param UriInterface $rel  Relative URI
     *
     * @return UriInterface
     * @link http://tools.ietf.org/html/rfc3986#section-5.2
     */
    public static function uriResolve(UriInterface $base, UriInterface $rel)
    {
        if ((string) $rel === '') {
            // we can simply return the same base URI instance for this same-document reference
            return $base;
        }

        if ($rel->getScheme() !== '') {
            return $rel->withPath(self::removeDotSegments($rel->getPath()));
        }

        if (($targetAuthority = $rel->getAuthority()) !== '') {
            $targetPath = self::removeDotSegments($rel->getPath());
            $targetQuery = $rel->getQuery();
        } else {
            $targetAuthority = $base->getAuthority();
            if (($targetPath = $rel->getPath()) === '') {
                $targetPath = $base->getPath();
                $targetQuery = $rel->getQuery() ?: $base->getQuery();
            } else {
                if ($targetPath[0] !== '/') {
                    $basePath = $base->getPath();
                    if ($targetAuthority !== '' && $basePath === '') {
                        $targetPath = '/' . $targetPath;
                    } elseif (($lastSlashPos = strrpos($basePath, '/')) !== false) {
                        $targetPath = substr($basePath, 0, $lastSlashPos + 1) . $targetPath;
                    }
                }
                $targetPath = self::removeDotSegments($targetPath);
                $targetQuery = $rel->getQuery();
            }
        }

        return Factory::uri(HttpHelpers::createUriString(
            $base->getScheme(),
            $targetAuthority,
            $targetPath,
            $targetQuery,
            $rel->getFragment()
        ));
    }


    /**
     * Returns the string representation of an HTTP message.
     *
     * @param MessageInterface $message Message to convert to a string.
     *
     * @return string
     */
    public static function str(MessageInterface $message)
    {
        if ($message instanceof RequestInterface) {
            $msg = trim($message->getMethod() . ' '
                    . $message->getRequestTarget())
                . ' HTTP/' . $message->getProtocolVersion();
            if (!$message->hasHeader('host')) {
                $msg .= "\r\nHost: " . $message->getUri()->getHost();
            }
        } elseif ($message instanceof ResponseInterface) {
            $msg = 'HTTP/' . $message->getProtocolVersion() . ' '
                . $message->getStatusCode() . ' '
                . $message->getReasonPhrase();
        } else {
            throw new \InvalidArgumentException('Unknown message type');
        }

        foreach ($message->getHeaders() as $name => $values) {
            $msg .= "\r\n{$name}: " . implode(', ', $values);
        }

        return "{$msg}\r\n\r\n" . $message->getBody();
    }
}