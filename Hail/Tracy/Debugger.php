<?php
/**
 * This file is part of the Tracy (http://tracy.nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com) Modified by FlyingHail <flyinghail@msn.com>
 */

namespace Hail\Tracy;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Debugger: displays and logs errors.
 *
 */
class Debugger
{
    const VERSION = '2.4.6';

    /** server modes for Debugger::enable() */
    const
        DEVELOPMENT = false,
        PRODUCTION = true,
        DETECT = null;

    const COOKIE_SECRET = 'tracy-debug';

    /** @var bool in production mode is suppressed any debugging output */
    public static $productionMode = self::DETECT;

    /** @var bool whether to display debug bar in development mode */
    public static $showBar = true;

    /** @var bool */
    private static $enabled = false;

    /** @var string reserved memory; also prevents double rendering */
    private static $reserved;

    /** @var int initial output buffer level */
    private static $obLevel;

    /********************* errors and exceptions reporting ****************d*g**/

    /** @var bool|int determines whether any error will cause immediate death in development mode; if integer that it's matched against error severity */
    public static $strictMode = false;

    /** @var bool disables the @ (shut-up) operator so that notices and warnings are no longer hidden */
    public static $scream = false;

    /** @var array of callables specifies the functions that are automatically called after fatal error */
    public static $onFatalError = [];

    /********************* Debugger::dump() ****************d*g**/

    /** @var int  how many nested levels of array/object properties display by dump() */
    public static $maxDepth = 5;

    /** @var int  how long strings display by dump() */
    public static $maxLength = 150;

    /** @var bool display location by dump()? */
    public static $showLocation = false;

    /********************* logging ****************d*g**/

    /** @var string name of the directory where errors should be logged */
    private static $logDirectory;

    /** @var int  log bluescreen in production mode for this error severity */
    public static $logSeverity = 0;

    /** @var string|array email(s) to which send error notifications */
    private static $email;

    /** for LoggerInterface */
    const
        DEBUG = LogLevel::DEBUG,
        INFO = LogLevel::INFO,
        NOTICE = LogLevel::NOTICE,
        WARNING = LogLevel::WARNING,
        ERROR = LogLevel::ERROR,
        CRITICAL = LogLevel::CRITICAL,
        ALERT = LogLevel::ALERT,
        EMERGENCY = LogLevel::EMERGENCY;

    public static $errorMap = [
        E_ERROR => LogLevel::CRITICAL,
        E_WARNING => LogLevel::WARNING,
        E_PARSE => LogLevel::ALERT,
        E_NOTICE => LogLevel::NOTICE,
        E_CORE_ERROR => LogLevel::CRITICAL,
        E_CORE_WARNING => LogLevel::WARNING,
        E_COMPILE_ERROR => LogLevel::ALERT,
        E_COMPILE_WARNING => LogLevel::WARNING,
        E_USER_ERROR => LogLevel::ERROR,
        E_USER_WARNING => LogLevel::WARNING,
        E_USER_NOTICE => LogLevel::NOTICE,
        E_STRICT => LogLevel::NOTICE,
        E_RECOVERABLE_ERROR => LogLevel::ERROR,
        E_DEPRECATED => LogLevel::NOTICE,
        E_USER_DEPRECATED => LogLevel::NOTICE,
    ];

    public static $exceptionMap = [
        \ParseError::class => LogLevel::CRITICAL,
        \Throwable::class => LogLevel::ERROR,
    ];

    /********************* misc ****************d*g**/

    /** @var int timestamp with microseconds of the start of the request */
    public static $time;

    /** @var string URI pattern mask to open editor */
    public static $editor = 'editor://open/?file=%file&line=%line';

    /** @var array replacements in path */
    public static $editorMapping = [];

    /** @var string command to open browser (use 'start ""' in Windows) */
    public static $browser;

    /** @var string custom static error template */
    public static $errorTemplate;

    /** @var array */
    private static $cpuUsage;

    /********************* services ****************d*g**/

    /** @var BlueScreen */
    private static $blueScreen;

    /** @var Bar */
    private static $bar;

    /** @var LoggerInterface */
    private static $logger;

    /** @var ChromeLogger */
    private static $chromeLogger;

    /** @var Bar\TracePanel */
    private static $trace;

    /**
     * Enables displaying or logging errors and exceptions.
     *
     * @param  mixed  $mode         production, development mode, autodetection or IP address(es) whitelist.
     * @param  string $logDirectory error log directory
     * @param  string $email        administrator email; enables email sending in production mode
     *
     * @return void
     * @throws \LogicException
     */
    public static function enable($mode = null, $logDirectory = null, $email = null)
    {
        if ($mode !== null || self::$productionMode === null) {
            self::$productionMode = is_bool($mode) ? $mode : !self::detectDebugMode($mode);
        }

        self::$reserved = str_repeat('t', 30000);
        self::$time = START_TIME;
        self::$obLevel = ob_get_level();
        self::$cpuUsage = !self::$productionMode && function_exists('getrusage') ? getrusage() : null;

        // logging configuration
        if ($email !== null) {
            self::$email = $email;
        }

        if ($logDirectory !== null) {
            self::$logDirectory = $logDirectory;
        }

        if (self::$logDirectory && (!is_dir(self::$logDirectory) || !preg_match('#([a-z]+:)?[/\\\\]#Ai',
                    self::$logDirectory))
        ) {
            self::$logDirectory = null;
            self::exceptionHandler(new \RuntimeException('Logging directory not found or is not absolute path.'));
        }

        // php configuration
        if (function_exists('ini_set')) {
            ini_set('display_errors', self::$productionMode ? '0' : '1'); // or 'stderr'
            ini_set('html_errors', '0');
            ini_set('log_errors', '0');
        } else {
            if (($displayErrors = ini_get('display_errors')) != !self::$productionMode // intentionally ==
                && $displayErrors !== (self::$productionMode ? 'stderr' : 'stdout')
            ) {
                self::exceptionHandler(new \RuntimeException("Unable to set 'display_errors' because function ini_set() is disabled."));
            }
        }
        error_reporting(E_ALL);

        if (self::$enabled) {
            return;
        }

        define('PRODUCTION_MODE', self::$productionMode);

        register_shutdown_function([__CLASS__, 'shutdownHandler']);
        set_exception_handler([__CLASS__, 'exceptionHandler']);
        set_error_handler([__CLASS__, 'errorHandler']);

        if (!self::$productionMode) {
            Profiler::enable();
        }

        self::dispatch();
        self::$enabled = true;
    }


    /**
     * @return void
     *
     * @throws \LogicException
     */
    public static function dispatch()
    {
        if (self::$productionMode) {
            return;
        }

        if (headers_sent($file, $line) || ob_get_length()) {
            throw new \LogicException(
                __METHOD__ . '() called after some output has been sent. '
                . ($file ? "Output started at $file:$line." : 'Try Hail\Tracy\OutputDebugger to find where output started.')
            );
        }

        if (self::$enabled && session_status() !== PHP_SESSION_ACTIVE) {
            ini_set('session.use_cookies', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_trans_sid', '0');
            ini_set('session.cookie_path', '/');
            ini_set('session.cookie_httponly', '1');
            session_start();
        }

        if (self::getBar()->dispatchAssets()) {
            exit;
        }
    }

    /**
     * Renders loading <script>
     * @return void
     */
    public static function renderLoader()
    {
        if (!self::$productionMode) {
            self::getBar()->renderLoader();
        }
    }

    /**
     * @return bool
     */
    public static function isEnabled()
    {
        return self::$enabled;
    }


    /**
     * Shutdown handler to catch fatal errors and execute of the planned activities.
     *
     * @return void
     * @internal
     */
    public static function shutdownHandler()
    {
        if (!self::$reserved) {
            return;
        }

        $error = error_get_last();
        if (in_array($error['type'],
            [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_RECOVERABLE_ERROR, E_USER_ERROR], true)) {
            self::exceptionHandler(
                Helpers::fixStack(new \ErrorException($error['message'], 0, $error['type'], $error['file'],
                    $error['line'])),
                false
            );

        } elseif (self::$showBar && !self::$productionMode) {
            self::removeOutputBuffers(false);
            self::getBar()->render();
        }
    }

    public static function getExceptionLevel(\Throwable $exception): string
    {
        foreach (self::$exceptionMap as $class => $level) {
            if ($exception instanceof $class) {
                return $level;
            }
        }

        return LogLevel::ERROR;
    }

    /**
     * Handler to catch uncaught exception.
     *
     * @param \Exception|\Throwable $exception
     * @param bool                  $exit
     *
     * @return void
     * @internal
     */
    public static function exceptionHandler($exception, $exit = true)
    {
        if (!self::$reserved && $exit) {
            return;
        }
        self::$reserved = null;

        if (!headers_sent()) {
            http_response_code(isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'],
                'MSIE ') !== false ? 503 : 500);
            if (Helpers::isHtmlMode()) {
                header('Content-Type: text/html; charset=UTF-8');
            }
        }

        Helpers::improveException($exception);
        self::removeOutputBuffers(true);

        if (self::$productionMode) {
            try {
                self::log($exception, self::getExceptionLevel($exception));
            } catch (\Throwable $e) {
            }

            if (Helpers::isHtmlMode()) {
                $logged = empty($e);
                require self::$errorTemplate ?: __DIR__ . '/assets/Debugger/error.500.phtml';
            } elseif (PHP_SAPI === 'cli') {
                fwrite(STDERR, 'ERROR: application encountered an error and can not continue. '
                    . (isset($e) ? "Unable to log error.\n" : "Error was logged.\n"));
            }

        } elseif (!connection_aborted() && (Helpers::isHtmlMode() || Helpers::isAjax())) {
            self::getBlueScreen()->render($exception);
            if (self::$showBar) {
                self::getBar()->render();
            }

        } else {
            $level = self::getExceptionLevel($exception);
            self::chromeLog($exception, $level);

            $s = get_class($exception) . ($exception->getMessage() === '' ? '' : ': ' . $exception->getMessage())
                . ' in ' . $exception->getFile() . ':' . $exception->getLine()
                . "\nStack trace:\n" . $exception->getTraceAsString();
            try {
                $file = self::log($exception, $level);
                if ($file && !headers_sent()) {
                    header("X-Tracy-Error-Log: $file");
                }
                echo "$s\n" . ($file ? "(stored in $file)\n" : '');
                if ($file && self::$browser) {
                    exec(self::$browser . ' ' . escapeshellarg($file));
                }
            } catch (\Throwable $e) {
                echo "$s\nUnable to log error: {$e->getMessage()}\n";
            } catch (\Exception $e) {
                echo "$s\nUnable to log error: {$e->getMessage()}\n";
            }
        }

        try {
            $e = null;
            foreach (self::$onFatalError as $handler) {
                call_user_func($handler, $exception);
            }
        } catch (\Throwable $e) {
        }

        if ($e) {
            try {
                self::log($e, self::getExceptionLevel($e));
            } catch (\Throwable $e) {
            }
        }

        if ($exit) {
            exit(255);
        }
    }

    public static function getErrorLevel($code)
    {
        return self::$errorMap[$code] ?? LogLevel::CRITICAL;
    }

    /**
     * Handler to catch warnings and notices.
     *
     * @return bool   FALSE to call normal error handler, NULL otherwise
     * @throws \ErrorException
     * @internal
     */
    public static function errorHandler($severity, $message, $file, $line, $context)
    {
        if (self::$scream) {
            error_reporting(E_ALL);
        }

        if ($severity === E_RECOVERABLE_ERROR || $severity === E_USER_ERROR) {
            if (Helpers::findTrace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), '*::__toString')) {
                $previous = isset($context['e']) && ($context['e'] instanceof \Exception || $context['e'] instanceof \Throwable) ? $context['e'] : null;
                $e = new \ErrorException($message, 0, $severity, $file, $line, $previous);
                $e->context = $context;
                self::exceptionHandler($e);
            }

            $e = new \ErrorException($message, 0, $severity, $file, $line);
            $e->context = $context;
            throw $e;

        }

        if (($severity & error_reporting()) !== $severity) {
            return false; // calls normal error handler to fill-in error_get_last()
        }

        if (self::$productionMode && ($severity & self::$logSeverity) === $severity) {
            $e = new \ErrorException($message, 0, $severity, $file, $line);
            $e->context = $context;
            Helpers::improveException($e);
            try {
                self::log($e, self::getErrorLevel($severity));
            } catch (\Throwable $e) {
            } catch (\Exception $foo) {
            }

            return null;
        }

        if (!self::$productionMode && !isset($_GET['_tracy_skip_error'])
            && (is_bool(self::$strictMode) ? self::$strictMode : ((self::$strictMode & $severity) === $severity))
        ) {
            $e = new \ErrorException($message, 0, $severity, $file, $line);
            $e->context = $context;
            $e->skippable = true;
            self::exceptionHandler($e);
        }

        $message = 'PHP ' . Helpers::errorTypeToString($severity) . ": $message";
        $count = &self::getBar()->getPanel('Tracy:errors')->data["$file|$line|$message"];

        if ($count++) { // repeated error
            return null;

        }

        if (self::$productionMode) {
            try {
                self::log("$message in $file:$line", self::getErrorLevel($severity));
            } catch (\Throwable $e) {
            }

            return null;

        }

        self::chromeLog(new \ErrorException($message, 0, $severity, $file, $line), self::ERROR);

        return Helpers::isHtmlMode() || Helpers::isAjax() ? null : false; // FALSE calls normal error handler
    }


    private static function removeOutputBuffers($errorOccurred)
    {
        while (ob_get_level() > self::$obLevel) {
            $status = ob_get_status();
            if (in_array($status['name'], ['ob_gzhandler', 'zlib output compression'], true)) {
                break;
            }
            $fnc = $status['chunk_size'] || !$errorOccurred ? 'ob_end_flush' : 'ob_end_clean';
            if (!@$fnc()) { // @ may be not removable
                break;
            }
        }
    }


    /********************* services ****************d*g**/


    /**
     * @return BlueScreen
     */
    public static function getBlueScreen(): BlueScreen
    {
        if (!self::$blueScreen) {
            self::$blueScreen = new BlueScreen;
            self::$blueScreen->info = [
                'PHP ' . PHP_VERSION,
                $_SERVER['SERVER_SOFTWARE'] ?? null,
                'Tracy ' . self::VERSION,
            ];
        }

        return self::$blueScreen;
    }


    /**
     * @return Bar
     */
    public static function getBar(): Bar
    {
        if (!self::$bar) {
            self::$bar = new Bar;
            self::$bar->addPanel($info = new Bar\DefaultPanel('info'), 'Tracy:info');
            $info->cpuUsage = self::$cpuUsage;
            self::$bar->addPanel(new Bar\DefaultPanel('errors'), 'Tracy:errors'); // filled by errorHandler()
            self::$bar->addPanel(new Bar\RoutePanel(), 'Hail:route');
            self::$bar->addPanel(new Bar\SessionPanel(), 'Session');
            self::$bar->addPanel(new Bar\QueryPanel(), 'Query');
            self::$bar->addPanel(new Bar\ProfilerPanel(), 'Profile');
            if (extension_loaded('xdebug') && \ini_get('xdebug.auto_trace') !== 'on') {
                self::$bar->addPanel(static::getTrace(), 'Hail:Trace');
            }
            self::$bar->addPanel(new Bar\GitPanel(), 'Git');
            self::$bar->addPanel(new Bar\VendorPanel(), 'Vendor');
        }

        return self::$bar;
    }

    /**
     * @param LoggerInterface $logger
     */
    public static function setLogger(LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

    /**
     * @return LoggerInterface
     */
    public static function getLogger(): LoggerInterface
    {
        if (!self::$logger) {
            self::$logger = new Logger(self::$logDirectory, self::$email, self::getBlueScreen());
        }

        return self::$logger;
    }

    /********************* useful tools ****************d*g**/


    /**
     * Dumps information about a variable in readable format.
     *
     * @tracySkipLocation
     *
     * @param  mixed  variable to dump
     * @param  bool   return output instead of printing it? (bypasses $productionMode)
     *
     * @return mixed  variable itself or dump
     */
    public static function dump($var, $return = false)
    {
        if ($return) {
            ob_start();
            Dumper::dump($var, [
                Dumper::DEPTH => self::$maxDepth,
                Dumper::TRUNCATE => self::$maxLength,
            ]);

            return ob_get_clean();
        }

        if (!self::$productionMode) {
            Dumper::dump($var, [
                Dumper::DEPTH => self::$maxDepth,
                Dumper::TRUNCATE => self::$maxLength,
                Dumper::LOCATION => self::$showLocation,
            ]);
        }

        return $var;
    }

    /**
     * Starts/stops stopwatch.
     *
     * @param  string  name
     *
     * @return float   elapsed seconds
     */
    public static function timer($name = null)
    {
        static $time = [];
        $now = microtime(true);
        $delta = isset($time[$name]) ? $now - $time[$name] : 0;
        $time[$name] = $now;

        return $delta;
    }

    /**
     * Dumps information about a variable in Tracy Debug Bar.
     *
     * @tracySkipLocation
     *
     * @param  mixed  $var     variable to dump
     * @param  string $title   optional title
     * @param  array  $options dumper options
     *
     * @return mixed  variable itself
     */
    public static function barDump($var, $title = null, array $options = null)
    {
        if (!self::$productionMode) {
            static $panel;
            if (!$panel) {
                self::getBar()->addPanel($panel = new Bar\DefaultPanel('dumps'), 'Tracy:dumps');
            }
            $panel->data[] = [
                'title' => $title,
                'dump' => Dumper::toHtml($var, (array) $options + [
                        Dumper::DEPTH => self::$maxDepth,
                        Dumper::TRUNCATE => self::$maxLength,
                        Dumper::LOCATION => self::$showLocation ?: Dumper::LOCATION_CLASS | Dumper::LOCATION_SOURCE,
                    ]),
            ];
        }

        return $var;
    }


    /**
     * Logs message or exception.
     *
     * @param  string|\Exception|\Throwable
     *
     * @return mixed
     */
    public static function log($message, $priority = self::INFO)
    {
        return self::sendToLogger(self::getLogger(), $priority, $message);
    }

    protected static function sendToLogger($logger, $level, $message)
    {
        if ($message instanceof \Throwable) {
            return $logger->log($level, '{exception}', [
                'exception' => $message,
            ]);
        }

        return $logger->log($level, $message);
    }

    /**
     * Sends message to ChromeLogger console.
     *
     * @param  mixed $message message to log
     * @param string $priority
     *
     * @return bool    was successful?
     */
    public static function chromeLog($message, $priority = self::DEBUG)
    {
        if (!self::$productionMode) {
            return self::sendToLogger(self::getChromeLogger(), $priority, $message);
        }

        return false;
    }

    /**
     * @return ChromeLogger
     */
    public static function getChromeLogger(): ChromeLogger
    {
        if (self::$chromeLogger === null) {
            self::$chromeLogger = new ChromeLogger();
        }

        return self::$chromeLogger;
    }

    /**
     * Detects debug mode by IP address.
     *
     * @param  string|array $list IP addresses or computer names whitelist detection
     *
     * @return bool
     */
    public static function detectDebugMode($list = null)
    {
        $addr = $_SERVER['REMOTE_ADDR'] ?? php_uname('n');
        $secret = isset($_COOKIE[self::COOKIE_SECRET]) && is_string($_COOKIE[self::COOKIE_SECRET])
            ? $_COOKIE[self::COOKIE_SECRET]
            : null;
        $list = is_string($list)
            ? preg_split('#[,\s]+#', $list)
            : (array) $list;
        if (!isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $list[] = '127.0.0.1';
            $list[] = '::1';
        }

        return in_array($addr, $list, true) || in_array("$secret@$addr", $list, true);
    }

    /**
     * @return Bar\TracePanel
     */
    public static function getTrace(): Bar\TracePanel
    {
        return static::$trace ?? (static::$trace = new Bar\TracePanel(
                STORAGE_PATH . 'xdebugTrace.xt'
            ));
    }

    /**
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public static function writeToResponse(ResponseInterface $response)
    {
        if (self::$chromeLogger !== null) {
            $response = self::$chromeLogger->writeToResponse($response);
        }

        return $response;
    }
}