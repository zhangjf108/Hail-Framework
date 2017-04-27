<?php

namespace Hail\Http;

/**
 * Class SetCookie
 *
 * @package Hail
 */
class Cookie
{
    const SAMESITE_LAX = 'lax';
    const SAMESITE_STRICT = 'strict';

    public $prefix = '';
    public $domain = '';
    public $path = '/';
    public $secure = false;
    public $httpOnly = true;
    public $lifetime = 0;
    public $sameSite = null;

    /**
     * @var string[][][][]
     */
    protected $cookies = [];

    /**
     * Cookie constructor.
     *
     * @param array $config
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $config = [])
    {
        $this->prefix = $config['prefix'] ?? '';
        $this->domain = $config['domain'] ?? '';
        $this->path = $config['path'] ?? '/';
        $this->secure = $config['secure'] ?? false;
        $this->httpOnly = $config['httponly'] ?? true;
        $this->lifetime = $config['lifetime'] ?? true;

        $sameSite = $config['sameSite'] ?? null;
        if (!in_array($sameSite, [self::SAMESITE_LAX, self::SAMESITE_STRICT, null], true)) {
            throw new \InvalidArgumentException('The "sameSite" parameter value is not valid.');
        }

        $this->sameSite = $sameSite;
    }

    /**
     * @param string               $name
     * @param string               $value
     * @param string|int|\DateTime $time
     * @param string               $path
     * @param string               $domain
     * @param bool                 $secure
     * @param bool                 $httpOnly
     * @param string               $sameSite
     */
    public function set(
        string $name,
        string $value,
        $time = null,
        string $path = null,
        string $domain = null,
        bool $secure = null,
        bool $httpOnly = null,
        string $sameSite = null
    ): void {
        $name = $this->prefix . $name;
        $path = $path ?? $this->path;
        $domain = $domain ?? $this->domain;

        $this->cookies[$domain][$path][$name] = [
            $name,
            $value,
            $time ?? $this->lifetime,
            $path,
            $domain,
            $secure ?? $this->secure,
            $httpOnly ?? $this->httpOnly,
            $sameSite ?? $this->sameSite,
        ];
    }

    /**
     * @param string $name
     * @param string $path
     * @param string $domain
     */
    public function delete(string $name, string $path = null, string $domain = null): void
    {
        $this->set($name, '', 0, $path, $domain);
    }

    /**
     * @param string $name
     * @param string $path
     * @param string $domain
     */
    public function remove(string $name, string $path = null, string $domain = null): void
    {
        $path = $path ?? $this->path;
        $domain = $domain ?? $this->domain;

        unset($this->cookies[$domain][$path][$name]);
        if (empty($this->cookies[$domain][$path])) {
            unset($this->cookies[$domain][$path]);
            if (empty($this->cookies[$domain])) {
                unset($this->cookies[$domain]);
            }
        }
    }

    public function send(): void
    {
        $this->checkHeaders();

        foreach ($this->cookies as $paths) {
            foreach ($paths as $cookies) {
                foreach ($cookies as $cookie) {
                    [$name, $value, $time, $path, $domain, $secure, $httpOnly, $sameSite] = $cookie;
                    $sameSite = $sameSite ? "; samesite=$sameSite" : '';
                    setcookie(
                        $name,
                        $value,
                        $time ? $this->getExpiresTime($time) : 0,
                        $path . $sameSite,
                        $domain,
                        $secure,
                        $httpOnly
                    );
                }
            }
        }

        $this->removeDuplicateCookies();
    }

    public function headers(): string
    {
        $values = [];
        foreach ($this->cookies as $paths) {
            foreach ($paths as $cookies) {
                foreach ($cookies as $cookie) {
                    $values[] = $this->headerValue($cookie);
                }
            }
        }

        return $values;
    }

    protected function headerValue(array $cookie): string
    {
        [$name, $value, $time, $path, $domain, $secure, $httpOnly, $sameSite] = $cookie;

        $str = urlencode($name) . '=';
        if ('' === $value) {
            $str .= 'deleted; expires=' . gmdate('D, d-M-Y H:i:s T', NOW - 31536001) . '; max-age=-31536001';
        } else {
            $str .= urlencode($value);
            if (0 !== $time) {
                $time = $this->getExpiresTime($time);
                $str .= '; expires=' . gmdate('D, d-M-Y H:i:s T', $time) . '; max-age=' . ($time - NOW);
            }
        }

        if ($path) {
            $str .= '; path=' . $path;
        }
        if ($domain) {
            $str .= '; domain=' . $domain;
        }
        if (true === $secure) {
            $str .= '; secure';
        }
        if (true === $httpOnly) {
            $str .= '; httponly';
        }
        if (null !== $sameSite) {
            $str .= '; samesite=' . $sameSite;
        }

        return $str;
    }

    private function checkHeaders(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        if (headers_sent($file, $line)) {
            throw new \RuntimeException('Cannot send header after HTTP headers have been sent' . ($file ? " (output started at $file:$line)." : '.'));
        }

        if (ob_get_length() && !array_filter(ob_get_status(true), function ($i) {
                return !$i['chunk_size'];
            })
        ) {
            trigger_error('Possible problem: you are sending a HTTP header while already having some data in output buffer. Try Hail\Tracy\OutputDebugger or start session earlier.');
        }
    }

    /**
     * Removes duplicate cookies from response.
     *
     * @internal
     */
    private function removeDuplicateCookies(): void
    {
        if (headers_sent($file, $line) || ini_get('suhosin.cookie.encrypt')) {
            return;
        }

        $flatten = [];
        foreach (headers_list() as $header) {
            if (preg_match('#^Set-Cookie: .+?=#', $header, $m)) {
                $flatten[$m[0]] = $header;
                header_remove('Set-Cookie');
            }
        }
        foreach (array_values($flatten) as $key => $header) {
            header($header, $key === 0);
        }
    }

    /**
     * Convert to unix timestamp
     *
     * @param  string|int|\DateTimeInterface $time
     *
     * @return int
     */
    private function getExpiresTime($time): int
    {
        if ($time instanceof \DateTimeInterface) {
            return (int) $time->format('U');
        }

        if (is_numeric($time)) {
            // average year in seconds
            if ($time <= 31557600) {
                $time += NOW;
            }

            return (int) $time;
        }

        return (int) (new \DateTime($time))->format('U');
    }
}
