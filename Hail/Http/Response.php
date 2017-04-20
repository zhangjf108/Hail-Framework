<?php

declare(strict_types=1);

namespace Hail\Http;

use Hail\Http\Factory\StreamFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * @author Michael Dowling and contributors to guzzlehttp/psr7
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Response implements ResponseInterface
{
	use MessageTrait;

	/** @var array Map of standard HTTP status code/reason phrases */
	public static $phrases = [
		// INFORMATIONAL CODES
		100 => 'Continue',
		101 => 'Switching Protocols',
		102 => 'Processing',
		// SUCCESS CODES
		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information',
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',
		207 => 'Multi-status',
		208 => 'Already Reported',
		226 => 'IM used',
		// REDIRECTION CODES
		300 => 'Multiple Choices',
		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		306 => 'Switch Proxy', // Deprecated to 306 => '(Unused)'
		307 => 'Temporary Redirect',
		308 => 'Permanent Redirect',
		// CLIENT ERROR
		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Time-out',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Large',
		415 => 'Unsupported Media Type',
		416 => 'Requested range not satisfiable',
		417 => 'Expectation Failed',
		418 => 'I\'m a teapot',
		421 => 'Misdirected Request',
		422 => 'Unprocessable Entity',
		423 => 'Locked',
		424 => 'Failed Dependency',
		425 => 'Unordered Collection',
		426 => 'Upgrade Required',
		428 => 'Precondition Required',
		429 => 'Too Many Requests',
		431 => 'Request Header Fields Too Large',
		444 => 'Connection Closed Without Response',
		451 => 'Unavailable For Legal Reasons',
		// SERVER ERROR
		499 => 'Client Closed Request',
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Time-out',
		505 => 'HTTP Version not supported',
		506 => 'Variant Also Negotiates',
		507 => 'Insufficient Storage',
		508 => 'Loop Detected',
		510 => 'Not Extended',
		511 => 'Network Authentication Required',
		599 => 'Network Connect Timeout Error',
	];

	/** @var string */
	private $reasonPhrase = '';

	/** @var int */
	private $statusCode = 200;

	/**
	 * @param string|null|resource|StreamInterface $body    Response body
	 * @param int                                  $status  Status code
	 * @param array                                $headers Response headers
	 * @param string                               $version Protocol version
	 * @param string|null                          $reason  Reason phrase (when empty a default will be used based on the status code)
	 */
	public function __construct(
		$body = null,
		int $status = 200,
		array $headers = [],
		string $version = '1.1',
		$reason = null
	)
	{
		$this->statusCode = (int) $status;

		if ($body !== '' && $body !== null) {
			$this->stream = Factory::stream($body);
		}

		$this->setHeaders($headers);
		if ($reason === null && isset(self::$phrases[$this->statusCode])) {
			$this->reasonPhrase = self::$phrases[$status];
		} else {
			$this->reasonPhrase = (string) $reason;
		}

		$this->protocol = $version;
	}

	public function getStatusCode(): int
	{
		return $this->statusCode;
	}

	public function getReasonPhrase(): string
	{
		return $this->reasonPhrase;
	}

	public function withStatus($code, $reasonPhrase = ''): self
	{
		$new = clone $this;
		$new->statusCode = (int) $code;
		if ($reasonPhrase === '' && isset(self::$phrases[$new->statusCode])) {
			$reasonPhrase = self::$phrases[$new->statusCode];
		}
		$new->reasonPhrase = $reasonPhrase;

		return $new;
	}
}
