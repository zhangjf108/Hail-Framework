<?php

declare(strict_types=1);

namespace Hail\Http;

use Hail\Util\{
    ArrayDot, Arrays, Strings
};
use Psr\Http\Message\{
    ServerRequestInterface, UploadedFileInterface, UriInterface
};

/**
 * ServerRequest wrapper
 *
 * @package Hail\Http
 *
 * @author  Hao Feng <flyinghail@msn.com>
 */
class Request
{
    /**
     * @var ServerRequestInterface
     */
    protected $serverRequest;

    /**
     * @var ArrayDot
     */
    protected $input;

    /**
     * $this->input fill all params?
     *
     * @var bool
     */
    protected $all = false;

    /**
     * ServerRequestWrapper constructor.
     */
    public function __construct()
    {
        $this->input = Arrays::dot();
    }

    /**
     * @param ServerRequestInterface $serverRequest
     */
    public function setServerRequest(ServerRequestInterface $serverRequest): void
    {
        if ($this->serverRequest === $serverRequest) {
            return;
        }

        $this->serverRequest = $serverRequest;

        if ($this->input->all() !== []) {
            $this->input->replace([]);
            $this->all = false;
        }
    }

    /**
     * @return string
     */
    public function protocol(): string
    {
        return $this->serverRequest->getProtocolVersion();
    }

    /**
     * @return string
     */
    public function method(): string
    {
        return $this->serverRequest->getMethod();
    }

    /**
     * @return string
     */
    public function target(): string
    {
        return $this->serverRequest->getRequestTarget();
    }

    /**
     * @return UriInterface
     */
    public function uri(): UriInterface
    {
        return $this->serverRequest->getUri();
    }

    /**
     * @param array|null $values
     *
     * @return array
     */
    public function inputs(array $values = null): array
    {
        if ($values === null) {
            if (!$this->all) {
                return $this->input->all();
            }

            $values = $this->serverRequest->getQueryParams();
            if ($this->serverRequest->getMethod() !== 'GET') {
                $values = array_replace($values,
                    $this->serverRequest->getParsedBody()
                );
            }
            $this->all = true;
        }

        return $this->input->replace($values);
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return mixed
     */
    public function input(string $name, $value = null)
    {
        if ($value !== null) {
            !$this->all && $this->inputs();
            $this->input->set($name, $value);

            return $value;
        }

        if ($this->all || $this->input->has($name)) {
            return $this->input->get($name);
        }

        if ($this->serverRequest->getMethod() !== 'GET') {
            $found = $this->request($name) ?? $this->query($name);
        } else {
            $found = $this->query($name);
        }

        if ($found !== null) {
            $this->input->set($name, $found);
        }

        return $found;
    }

    /**
     * Delete from input
     *
     * @param string $name
     */
    public function delete(string $name): void
    {
        if (!$this->all) {
            $this->inputs();
        }

        $this->input->delete($name);
    }

    public function request(string $name = null)
    {
        return Arrays::get($this->serverRequest->getParsedBody(), $name);
    }

    public function query(string $name = null)
    {
        return Arrays::get($this->serverRequest->getQueryParams(), $name);
    }

    /**
     * @return array
     */
    public function files(): array
    {
        return $this->serverRequest->getUploadedFiles();
    }

    /**
     * @param string $name
     *
     * @return null|UploadedFileInterface
     */
    public function file(string $name): ?UploadedFileInterface
    {
        return Arrays::get($this->serverRequest->getUploadedFiles(), $name);
    }

    /**
     * @param string $name
     *
     * @return null|string
     */
    public function cookie(string $name): ?string
    {
        return $this->serverRequest->getCookieParams()[$name] ?? null;
    }

    /**
     * @param string $name
     *
     * @return null|string
     */
    public function server(string $name): ?string
    {
        return $this->serverRequest->getServerParams()[$name] ?? null;
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function attribute(string $name)
    {
        return $this->serverRequest->getAttribute($name);
    }

    /**
     * @param string $name
     *
     * @return string
     */
    public function header(string $name): string
    {
        return $this->serverRequest->getHeaderLine($name);
    }

    /**
     * @return bool
     */
    public function secure(): bool
    {
        return $this->serverRequest->getUri()->getScheme() === 'https';
    }

    /**
     * Is AJAX request?
     *
     * @return bool
     */
    public function ajax(): bool
    {
        return $this->serverRequest->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Determine if the request is the result of an PJAX call.
     *
     * @return bool
     */
    public function pjax(): bool
    {
        return $this->serverRequest->getHeaderLine('X-PJAX') === 'true';
    }

    /**
     * Determine if the request is sending JSON.
     *
     * @return bool
     */
    public function json(): bool
    {
        return Strings::contains(
            $this->serverRequest->getHeaderLine('Content-Type') ?? '', ['/json', '+json']
        );
    }

    /**
     * Determine if the current request probably expects a JSON response.
     *
     * @return bool
     */
    public function expectsJson(): bool
    {
        return ($this->ajax() && !$this->pjax()) || $this->wantsJson();
    }

    /**
     * Determine if the current request is asking for JSON in return.
     *
     * @return bool
     */
    public function wantsJson(): bool
    {
        $acceptable = $this->serverRequest->getHeaderLine('Accept');

        return $acceptable !== null && Strings::contains($acceptable, ['/json', '+json']);
    }
}