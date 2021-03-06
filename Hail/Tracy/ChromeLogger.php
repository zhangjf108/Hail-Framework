<?php

namespace Hail\Tracy;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;

/**
 * ChromeLogger console logger.
 *
 * @see https://craig.is/writing/chrome-logger
 * @see https://developer.mozilla.org/en-US/docs/Tools/Web_Console/Console_messages#Server
 */
class ChromeLogger implements LoggerInterface
{
    use LoggerTrait;

    const VERSION = '4.1.0';

    const COLUMN_LOG = 'log';
    const COLUMN_BACKTRACE = 'backtrace';
    const COLUMN_TYPE = 'type';
    const CLASS_NAME = 'type';
    const HEADER_NAME = 'X-ChromeLogger-Data';

    const LOG = 'log';
    const WARN = 'warn';
    const ERROR = 'error';
    const INFO = 'info';

    // TODO add support for groups and tables?
    const GROUP = 'group';
    const GROUP_END = 'groupEnd';
    const GROUP_COLLAPSED = 'groupCollapsed';
    const TABLE = 'table';
    const DATETIME_FORMAT = 'Y-m-d\\TH:i:s\\Z'; // ISO-8601 UTC date/time format
    const LIMIT_WARNING = 'Beginning of log entries omitted - total header size over Chrome\'s internal limit!';

    /**
     * @var int header size limit (in bytes, defaults to 240KB)
     */
    protected $limit = 245760;

    /**
     * @var array[][]
     */
    protected $entries = [];

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        $this->entries[] = [$level, $message, $context];
    }

    /**
     * Allows you to override the internal 240 KB header size limit.
     *
     * (Chrome has a 250 KB limit for the total size of all headers.)
     *
     * @see https://cs.chromium.org/chromium/src/net/http/http_stream_parser.h?q=ERR_RESPONSE_HEADERS_TOO_BIG&sq=package:chromium&dr=C&l=159
     *
     * @param int $limit header size limit (in bytes)
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;
    }

    /**
     * @return int header size limit (in bytes)
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * Adds headers for recorded log-entries in the ChromeLogger format, and clear the internal log-buffer.
     *
     * (You should call this at the end of the request/response cycle in your PSR-7 project, e.g.
     * immediately before emitting the Response.)
     *
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public function writeToResponse(ResponseInterface $response)
    {
        if ($this->entries !== []) {
            $value = $this->getHeaderValue();

            $this->entries = [];

            return $response->withHeader(self::HEADER_NAME, $value);
        }

        return $response;
    }

    /**
     * Emit the header for recorded log-entries directly using `header()`, and clear the internal buffer.
     *
     * (You can use this in a non-PSR-7 project, immediately before you start emitting the response body.)
     *
     * @throws \RuntimeException if you've already started emitting the response body
     *
     * @return void
     */
    public function emitHeader()
    {
        if (headers_sent()) {
            throw new \RuntimeException('unable to emit ChromeLogger header: headers have already been sent');
        }

        header(self::HEADER_NAME . ': ' . $this->getHeaderValue());

        $this->entries = [];
    }

    /**
     * @return string raw value for the X-ChromeLogger-Data header
     */
    protected function getHeaderValue()
    {
        $data = $this->createData($this->entries);

        $value = $this->encodeData($data);

        if (strlen($value) > $this->limit) {
            $data['rows'][] = $this->createEntryData(
                [LogLevel::WARNING, self::LIMIT_WARNING, []]
            );

            // NOTE: the strategy here is to calculate an estimated overhead, based on the number
            //       of rows - because the size of each row may vary, this isn't necessarily accurate,
            //       so we may need repeat this more than once.
            while (strlen($value) > $this->limit) {
                $num_rows = count($data['rows']); // current number of rows
                $row_size = strlen($value) / $num_rows; // average row-size
                $max_rows = (int) floor(($this->limit * 0.95) / $row_size); // 5% under the likely max. number of rows
                $excess = max(1, $num_rows - $max_rows);

                // Remove excess rows and try encoding again:
                $data['rows'] = array_slice($data['rows'], $excess);
                $value = $this->encodeData($data);
            }
        }

        return $value;
    }

    /**
     * Encodes the ChromeLogger-compatible data-structure in JSON/base64-format
     *
     * @param array $data header data
     *
     * @return string
     */
    protected function encodeData(array $data)
    {
        $json = json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return base64_encode($json);
    }

    /**
     * Internally builds the ChromeLogger-compatible data-structure from internal log-entries.
     *
     * @param array[][] $entries
     *
     * @return array
     */
    protected function createData(array $entries)
    {
        $rows = [];

        foreach ($entries as $entry) {
            $rows[] = $this->createEntryData($entry);
        }

        return [
            'version' => self::VERSION,
            'columns' => [self::COLUMN_LOG, self::COLUMN_TYPE, self::COLUMN_BACKTRACE],
            'rows' => $rows,
        ];
    }

    /**
     * Encode an individual LogEntry in ChromeLogger-compatible format
     *
     * @param array $entry
     *
     * @return array log entry in ChromeLogger row-format
     */
    protected function createEntryData(array $entry)
    {
        // NOTE: "log" level type is deliberately omitted from the following map, since
        //       it's the default entry-type in ChromeLogger, and can be omitted.

        static $LEVELS = [
            LogLevel::DEBUG => self::LOG,
            LogLevel::INFO => self::INFO,
            LogLevel::NOTICE => self::INFO,
            LogLevel::WARNING => self::WARN,
            LogLevel::ERROR => self::ERROR,
            LogLevel::CRITICAL => self::ERROR,
            LogLevel::ALERT => self::ERROR,
            LogLevel::EMERGENCY => self::ERROR,
        ];

        $row = [];

        [$level, $message, $context] = $entry;

        $data = [
            str_replace('%', '%%', Dumper::interpolate($message, $context)),
        ];

        if (count($context)) {
            $context = $this->sanitize($context);

            $data = array_merge($data, $context);
        }

        $row[] = $data;

        $row[] = $LEVELS[$level] ?? self::LOG;

        if (isset($context['exception'])) {
            // NOTE: per PSR-3, this reserved key could be anything, but if it is an Exception, we
            //       can use that Exception to obtain a stack-trace for output in ChromeLogger.
            $exception = $context['exception'];

            if ($exception instanceof \Exception || $exception instanceof \Error) {
                $row[] = $exception->__toString();
            }
        }

        // Optimization: ChromeLogger defaults to "log" if no entry-type is specified.
        if ($row[1] === self::LOG) {
            if (count($row) === 2) {
                unset($row[1]);
            } else {
                $row[1] = '';
            }
        }

        return $row;
    }

    /**
     * Internally marshall and sanitize context values, producing a JSON-compatible data-structure.
     *
     * @param mixed  $data      any PHP object, array or value
     * @param true[] $processed map where SPL object-hash => TRUE (eliminates duplicate objects from data-structures)
     *
     * @return mixed marshalled and sanitized context
     */
    protected function sanitize($data, &$processed = [])
    {
        if (is_array($data)) {
            /**
             * @var array $data
             */
            foreach ($data as $name => $value) {
                $data[$name] = $this->sanitize($value, $processed);
            }

            return $data;
        }

        if (is_object($data)) {
            /**
             * @var object $data
             */
            $class_name = get_class($data);
            $hash = spl_object_hash($data);

            if (isset($processed[$hash])) {
                // NOTE: duplicate objects (circular references) are omitted to prevent recursion.

                return [self::CLASS_NAME => $class_name];
            }

            $processed[$hash] = true;

            if ($data instanceof \JsonSerializable) {
                // NOTE: this doesn't serialize to JSON, it only marshalls to a JSON-compatible data-structure
                $data = $this->sanitize($data->jsonSerialize(), $processed);
            } elseif ($data instanceof \DateTimeInterface) {
                $data = $this->extractDateTimeProperties($data);
            } elseif ($data instanceof \Exception || $data instanceof \Error) {
                $data = $this->extractExceptionProperties($data);
            } else {
                $data = $this->sanitize($this->extractObjectProperties($data), $processed);
            }

            return array_merge([self::CLASS_NAME => $class_name], $data);
        }

        if (is_scalar($data)) {
            return $data; // bool, int, float
        }

        if (is_resource($data)) {
            $resource = explode('#', (string) $data);

            return [
                self::CLASS_NAME => 'resource<' . get_resource_type($data) . '>',
                'id' => array_pop($resource),
            ];
        }

        return null; // omit any other unsupported types (e.g. resource handles)
    }

    /**
     * @param \DateTimeInterface $datetime
     *
     * @return array
     */
    protected function extractDateTimeProperties(\DateTimeInterface $datetime)
    {
        $utc = date_create_from_format('U', $datetime->format('U'), timezone_open('UTC'));

        return [
            'datetime' => $utc->format(self::DATETIME_FORMAT),
            'timezone' => $datetime->getTimezone()->getName(),
        ];
    }

    /**
     * @param object $object
     *
     * @return array
     */
    protected function extractObjectProperties($object)
    {
        $properties = [];

        $reflection = new \ReflectionClass(get_class($object));

        // obtain public, protected and private properties of the class itself:

        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue; // omit static properties
            }

            $property->setAccessible(true);

            $properties["\${$property->name}"] = $property->getValue($object);
        }

        // obtain any inherited private properties from parent classes:

        while ($reflection = $reflection->getParentClass()) {
            foreach ($reflection->getProperties(\ReflectionProperty::IS_PRIVATE) as $property) {
                $property->setAccessible(true);

                $properties["{$reflection->name}::\${$property->name}"] = $property->getValue($object);
            }
        }

        return $properties;
    }

    /**
     * @param \Throwable $exception
     *
     * @return array
     */
    protected function extractExceptionProperties($exception)
    {
        $previous = $exception->getPrevious();

        return [
            '$message' => $exception->getMessage(),
            '$file' => $exception->getFile(),
            '$code' => $exception->getCode(),
            '$line' => $exception->getLine(),
            '$previous' => $previous ? $this->extractExceptionProperties($previous) : null,
        ];
    }
}

