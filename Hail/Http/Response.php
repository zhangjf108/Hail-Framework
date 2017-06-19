<?php
/*
 * This class some code from the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 */

declare(strict_types=1);

namespace Hail\Http;

use Hail\Application;
use Hail\Exception\BadRequestException;
use Hail\Util\Json;
use Hail\Http\Message\Response as ResponseMessage;
use Hail\Util\MimeType;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Class Response
 *
 * @package Hail
 */
class Response
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Header
     */
    public $header;

    /**
     * @var Cookie
     */
    public $cookie;

    /**
     * @var string
     */
    protected $output;

    /**
     * @var int
     */
    protected $status;

    /**
     * @var string
     */
    protected $reason;

    /**
     * @var string
     */
    protected $version;

    public function __construct(Application $app, Request $request)
    {
        $this->app = $app;
        $this->request = $request;

        $this->cookie = new Cookie(
            $app->config('cookie')
        );
        $this->header = new Header();

        $this->status(200);
        $this->setDate(\DateTime::createFromFormat('U', (string) NOW));
    }

    /**
     * @param int|null    $code
     * @param string|null $reason
     *
     * @return self|int
     */
    public function status(int $code = null, string $reason = null)
    {
        if ($code === null) {
            return $this->status;
        }

        if (!isset(ResponseMessage::$phrases[$code])) {
            throw new \InvalidArgumentException("The HTTP status code is not valid: {$code}");
        }

        $this->status = $code;

        if ($reason === null) {
            $reason = ResponseMessage::$phrases[$code];
        }
        $this->reason = $reason;

        return $this;
    }

    /**
     * @param string|null $phrase
     *
     * @return self|string
     */
    public function reason(string $phrase = null)
    {
        if ($phrase === null) {
            return $this->reason;
        }

        $this->reason = $phrase;

        return $this;
    }

    /**
     * Protocol version
     *
     * @param string|null $version
     *
     * @return self|string
     */
    public function version(string $version = null)
    {
        if ($version === null) {
            return $this->version;
        }

        $this->version = $version;

        return $this;
    }

    public function to(string $name = null): self
    {
        if ($name === null) {
            $handler = $this->app->handler();
            $app = isset($handler['app']) ? '.' . $handler['app'] : '';
            $name = $this->app->config('app.output' . $app);
        }

        if (!in_array($name, ['json', 'template', 'redirect', 'html', 'text', 'empty'], true)) {
            throw new \InvalidArgumentException('Output type not defined: ' . $name);
        }

        $this->output = $name;

        return $this;
    }

    public function empty(int $status = 204)
    {
        return $this->status($status)->response(
            Factory::streamFromFile('php://temp', 'r')
        );
    }

    /**
     * @param string|UriInterface $uri
     *
     * @return ResponseInterface
     */
    public function redirect($uri): ResponseInterface
    {
        if (!is_string($uri) && !$uri instanceof UriInterface) {
            throw new \InvalidArgumentException('Uri MUST be a string or Psr\Http\Message\UriInterface instance; received "' .
                (is_object($uri) ? get_class($uri) : gettype($uri)) . '"');
        }

        $this->header->set('Location', (string) $uri);

        return $this->empty();
    }

    public function notModified(): ResponseInterface
    {
        // remove headers that MUST NOT be included with 304 Not Modified responses
        foreach (
            [
                'Allow',
                'Content-Encoding',
                'Content-Language',
                'Content-Length',
                'Content-MD5',
                'Content-Type',
                'Last-Modified',
            ] as $header
        ) {
            $this->header->remove($header);
        }

        return $this->empty(304);
    }

    /**
     * Get or set template name
     *
     * @param string|array|null $name
     * @param array             $params
     *
     * @return ResponseInterface
     */
    public function template($name, array $params = []): ResponseInterface
    {
        if (is_array($name)) {
            $params = $name;
            $name = null;
        }

        if ($name === null) {
            $handler = $this->app->handler();
            if ($handler instanceof \Closure) {
                throw new \LogicException('Con not build the template from handler!');
            }

            $name = ltrim($handler['app'] . '/' . $handler['controller'] . '/' . $handler['action'], '/');
        } elseif (!is_string($name)) {
            throw new \InvalidArgumentException('Must defined template name');
        }

        $response = $this->response();

        return $this->app->render($response, $name, $params);
    }

    public function json($data, $strict = false): ResponseInterface
    {
        if (is_resource($data)) {
            throw new \InvalidArgumentException('Cannot JSON encode resources');
        }

        if (is_array($data) && !isset($data['ret'])) {
            $data['ret'] = 0;
            $data['msg'] = '';
        }

        $data = Json::encode($data,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
        );

        return $this->print($data, $strict ? 'application/json' : '');
    }

    public function text(string $text, $strict = false): ResponseInterface
    {
        return $this->print($text, $strict ? 'text/plain; charset=utf-8' : '');
    }

    public function html(string $html, $strict = false): ResponseInterface
    {
        return $this->print($html, $strict ? 'text/html; charset=utf-8' : '');
    }

    /**
     * @param string $str
     * @param string $contentType
     *
     * @return ResponseInterface
     */
    public function print(
        string $str,
        string $contentType = null
    ): ResponseInterface {
        $body = Factory::streamFromFile('php://temp', 'wb+');
        $body->write($str);
        $body->rewind();

        if ($contentType) {
            $this->header->set('Content-Type', $contentType);
        }

        return $this->status(200)->response($body);
    }

    public function file(string $file, $name = null, $download = true)
    {
        if (!is_file($file)) {
            throw new \LogicException("File '$file' doesn't exist.");
        }

        $extension = substr($file, strrpos($file, '.') + 1);
        $this->header->set('Content-Type',
            MimeType::getMimeType($extension) ?? 'application/octet-stream'
        );

        $disposition = $download ? Header::DISPOSITION_ATTACHMENT : Header::DISPOSITION_INLINE;
        $this->header->set('Content-Disposition',
            $this->header->makeDisposition($disposition, basename($file), $name)
        );

        $size = $length = filesize($file);

        $this->header->set('Accept-Ranges', 'bytes');
        if (preg_match('#^bytes=(\d*)-(\d*)\z#', $this->request->header('Range'), $matches)) {
            list(, $start, $end) = $matches;

            if ($start === '') {
                $start = max(0, $size - $end);
                $end = $size - 1;
            } elseif ($end === '' || $end > $size - 1) {
                $end = $size - 1;
            }

            if ($end < $start) {
                return $this->empty(416); // requested range not satisfiable
            }

            $this->status(206);
            $this->header->set('Content-Range', 'bytes ' . $start . '-' . $end . '/' . $size);
            $length = $end - $start + 1;
        } else {
            $this->header->set('Content-Range', 'bytes 0-' . ($size - 1) . '/' . $size);
        }

        $this->header->set('Content-Length', $length);

        $this->app->emitter(new Emitter\SapiStream());

        return $this->response(
            Factory::streamFromFile($file, 'rb')
        );
    }

    public function response($body = null): ResponseInterface
    {
        if (($this->status >= 100 && $this->status < 200) || $this->status === 204 || $this->status === 304) {
            $body = null;
            $this->header->remove('Content-Type');
            $this->header->remove('Content-Length');
        } else {
            if ($this->header->has('Transfer-Encoding')) {
                $this->header->remove('Content-Length');
            }

            if ($this->request->method() === 'HEAD') {
                $body = null;
            }
        }

        if (!$this->version && 'HTTP/1.0' !== $this->request->server('SERVER_PROTOCOL')) {
            $this->version = '1.1';
        }

        // Check if we need to send extra expire info headers
        if ('1.0' === $this->version && false !== strpos($this->header->get('Cache-Control'), 'no-cache')) {
            $this->header->set('Pragma', 'no-cache');
            $this->header->set('Expires', -1);
        }

        // Checks if we need to remove Cache-Control for SSL encrypted downloads when using IE < 9.
        if (
            true === $this->request->secure() &&
            false !== stripos($this->header->get('Content-Disposition'), 'attachment') &&
            preg_match('/MSIE (.*?);/i', $this->request->header('User-Agent'), $match) === 1
        ) {
            if ((int) preg_replace('/(MSIE )(.*?);/', '$2', $match[0]) < 9) {
                $this->header->remove('Cache-Control');
            }
        }

        $headers = $this->header->all();
        $this->cookie->inject($headers);

        return Factory::response($this->status, $body, $headers, $this->version, $this->reason);
    }

    public function default($return): ResponseInterface
    {
        if ($this->output === null) {
            $this->to();
        }

        switch ($this->output) {
            case 'json':
                if ($return === true) {
                    $return = [];
                }

                return $this->json($return);

            case 'text':
                return $this->text($return);

            case 'html':
                return $this->html($return);

            case 'template':
                return $this->template(null, $return);

            case 'redirect':
                return $this->redirect($return);

            case 'file':
                return $this->file($return);

            default:
                return $this->empty();
        }
    }

    /**
     * @param array $to
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws BadRequestException
     */
    public function forward(array $to)
    {
        $this->app->params($to['params'] ?? null);

        return $this->app->handle(
            $this->app->handler($to)
        );
    }

    public function error($code, $msg = null)
    {
        return $this->forward([
            'controller' => 'Error',
            'params' => [
                'error' => $code,
                'message' => $msg,
            ],
        ]);
    }

    public function cookie(string $name, string $value, $time = 0)
    {
        $this->cookie->set($name, $value, $time);
    }

    public function headers(array $headers = null)
    {
        if ($headers === null) {
            return $this->header->all();
        }

        $this->header->replace($headers);

        return $this;
    }

    public function header(string $header, $value = null, $replace = false)
    {
        if ($value === null) {
            return $this->header->get($header);
        }

        $this->header->set($header, $value, $replace);

        return $this;
    }

    /**
     * Returns the Date header as a DateTime instance.
     *
     * @return \DateTime A \DateTime instance
     *
     * @throws \RuntimeException When the header is not parseable
     *

     */
    public function getDate()
    {
        /*
            RFC2616 - 14.18 says all Responses need to have a Date.
            Make sure we provide one even if it the header
            has been removed in the meantime.
         */
        if (!$this->header->has('Date')) {
            $this->setDate(\DateTime::createFromFormat('U', (string) NOW));
        }

        return $this->header->getDate('Date');
    }

    /**
     * Sets the Date header.
     *
     * @param \DateTime $date A \DateTime instance
     *
     * @return $this
     */
    public function setDate(\DateTime $date)
    {
        $date->setTimezone(new \DateTimeZone('UTC'));
        $this->header->set('Date', $date->format('D, d M Y H:i:s') . ' GMT');

        return $this;
    }

    /**
     * Sends HTTP headers.
     *
     * @return $this
     */
    public function sendHeaders()
    {
        // headers have already been sent by the developer
        if (headers_sent()) {
            return $this;
        }

        /* RFC2616 - 14.18 says all Responses need to have a Date */
        if (!$this->header->has('Date')) {
            $this->setDate(\DateTime::createFromFormat('U', (string) NOW));
        }

        // headers
        foreach ($this->header->all() as $name => $values) {
            foreach ($values as $value) {
                header($name . ': ' . $value, false, $this->status);
            }
        }

        // status
        header(sprintf('HTTP/%s %s %s', $this->version, $this->status, $this->reason), true, $this->status);

        // cookies
        $this->cookie->send();

        return $this;
    }

    /**
     * Marks the response as "private".
     *
     * It makes the response ineligible for serving other clients.
     *
     * @return $this
     */
    public function setPrivate()
    {
        $this->header->removeCacheControlDirective('public');
        $this->header->addCacheControlDirective('private');

        return $this;
    }

    /**
     * Marks the response as "public".
     *
     * It makes the response eligible for serving other clients.
     *
     * @return $this
     */
    public function setPublic()
    {
        $this->header->addCacheControlDirective('public');
        $this->header->removeCacheControlDirective('private');

        return $this;
    }

    /**
     * Returns the age of the response.
     *
     * @return int The age of the response in seconds
     */
    public function getAge()
    {
        if (null !== $age = $this->header->get('Age')) {
            return (int) $age;
        }

        return max(NOW - $this->getDate()->format('U'), 0);
    }

    /**
     * Marks the response stale by setting the Age header to be equal to the maximum age of the response.
     *
     * @return $this
     */
    public function expire()
    {
        if ($this->getTtl() > 0) {
            $this->header->set('Age', $this->getMaxAge());
        }

        return $this;
    }

    /**
     * Returns the value of the Expires header as a DateTime instance.
     *
     * @return \DateTime|null A DateTime instance or null if the header does not exist
     */
    public function getExpires()
    {
        try {
            return $this->header->getDate('Expires');
        } catch (\RuntimeException $e) {
            // according to RFC 2616 invalid date formats (e.g. "0" and "-1") must be treated as in the past
            return \DateTime::createFromFormat(DATE_RFC2822, 'Sat, 01 Jan 00 00:00:00 +0000');
        }
    }

    /**
     * Sets the Expires HTTP header with a DateTime instance.
     *
     * Passing null as value will remove the header.
     *
     * @param \DateTime|null $date A \DateTime instance or null to remove the header
     *
     * @return $this
     */
    public function setExpires(\DateTime $date = null)
    {
        if (null === $date) {
            $this->header->remove('Expires');
        } else {
            $date = clone $date;
            $date->setTimezone(new \DateTimeZone('UTC'));
            $this->header->set('Expires', $date->format('D, d M Y H:i:s') . ' GMT');
        }

        return $this;
    }

    /**
     * Returns the number of seconds after the time specified in the response's Date
     * header when the response should no longer be considered fresh.
     *
     * First, it checks for a s-maxage directive, then a max-age directive, and then it falls
     * back on an expires header. It returns null when no maximum age can be established.
     *
     * @return int|null Number of seconds
     */
    public function getMaxAge()
    {
        if ($this->header->hasCacheControlDirective('s-maxage')) {
            return (int) $this->header->getCacheControlDirective('s-maxage');
        }

        if ($this->header->hasCacheControlDirective('max-age')) {
            return (int) $this->header->getCacheControlDirective('max-age');
        }

        if (null !== $this->getExpires()) {
            return $this->getExpires()->format('U') - $this->getDate()->format('U');
        }

        return null;
    }

    /**
     * Sets the number of seconds after which the response should no longer be considered fresh.
     *
     * This methods sets the Cache-Control max-age directive.
     *
     * @param int $value Number of seconds
     *
     * @return $this
     */
    public function setMaxAge($value)
    {
        $this->header->addCacheControlDirective('max-age', $value);

        return $this;
    }

    /**
     * Sets the number of seconds after which the response should no longer be considered fresh by shared caches.
     *
     * This methods sets the Cache-Control s-maxage directive.
     *
     * @param int $value Number of seconds
     *
     * @return $this
     */
    public function setSharedMaxAge($value)
    {
        $this->setPublic();
        $this->header->addCacheControlDirective('s-maxage', $value);

        return $this;
    }

    /**
     * Returns the response's time-to-live in seconds.
     *
     * It returns null when no freshness information is present in the response.
     *
     * When the responses TTL is <= 0, the response may not be served from cache without first
     * revalidating with the origin.
     *
     * @return int|null The TTL in seconds
     */
    public function getTtl()
    {
        if (null !== $maxAge = $this->getMaxAge()) {
            return $maxAge - $this->getAge();
        }

        return null;
    }

    /**
     * Sets the response's time-to-live for shared caches.
     *
     * This method adjusts the Cache-Control/s-maxage directive.
     *
     * @param int $seconds Number of seconds
     *
     * @return $this
     */
    public function setTtl($seconds)
    {
        $this->setSharedMaxAge($this->getAge() + $seconds);

        return $this;
    }

    /**
     * Sets the response's time-to-live for private/client caches.
     *
     * This method adjusts the Cache-Control/max-age directive.
     *
     * @param int $seconds Number of seconds
     *
     * @return $this
     */
    public function setClientTtl($seconds)
    {
        $this->setMaxAge($this->getAge() + $seconds);

        return $this;
    }

    /**
     * Returns the Last-Modified HTTP header as a DateTime instance.
     *
     * @return \DateTime|null A DateTime instance or null if the header does not exist
     *
     * @throws \RuntimeException When the HTTP header is not parseable
     */
    public function getLastModified()
    {
        return $this->header->getDate('Last-Modified');
    }

    /**
     * Sets the Last-Modified HTTP header with a DateTime instance.
     *
     * Passing null as value will remove the header.
     *
     * @param \DateTime|null $date A \DateTime instance or null to remove the header
     *
     * @return $this
     */
    public function setLastModified(\DateTime $date = null)
    {
        if (null === $date) {
            $this->header->remove('Last-Modified');
        } else {
            $date = clone $date;
            $date->setTimezone(new \DateTimeZone('UTC'));
            $this->header->set('Last-Modified', $date->format('D, d M Y H:i:s') . ' GMT');
        }

        return $this;
    }

    /**
     * Returns the literal value of the ETag HTTP header.
     *
     * @return string|null The ETag HTTP header or null if it does not exist
     */
    public function getEtag()
    {
        return $this->header->get('ETag');
    }

    /**
     * Sets the ETag value.
     *
     * @param string|null $etag The ETag unique identifier or null to remove the header
     * @param bool        $weak Whether you want a weak ETag or not
     *
     * @return $this
     */
    public function setEtag($etag = null, $weak = false)
    {
        if (null === $etag) {
            $this->header->remove('Etag');
        } else {
            if (0 !== strpos($etag, '"')) {
                $etag = '"' . $etag . '"';
            }
            $this->header->set('ETag', (true === $weak ? 'W/' : '') . $etag);
        }

        return $this;
    }

    /**
     * Sets the response's cache headers (validation and/or expiration).
     *
     * Available options are: etag, last_modified, max_age, s_maxage, private, and public.
     *
     * @param array $options An array of cache options
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function setCache(array $options)
    {
        if ($diff = array_diff(array_keys($options),
            ['etag', 'last_modified', 'max_age', 's_maxage', 'private', 'public'])
        ) {
            throw new \InvalidArgumentException(sprintf('Response does not support the following options: "%s".',
                implode('", "', array_values($diff))));
        }
        if (isset($options['etag'])) {
            $this->setEtag($options['etag']);
        }
        if (isset($options['last_modified'])) {
            $this->setLastModified($options['last_modified']);
        }
        if (isset($options['max_age'])) {
            $this->setMaxAge($options['max_age']);
        }
        if (isset($options['s_maxage'])) {
            $this->setSharedMaxAge($options['s_maxage']);
        }
        if (isset($options['public'])) {
            if ($options['public']) {
                $this->setPublic();
            } else {
                $this->setPrivate();
            }
        }
        if (isset($options['private'])) {
            if ($options['private']) {
                $this->setPrivate();
            } else {
                $this->setPublic();
            }
        }

        return $this;
    }
}