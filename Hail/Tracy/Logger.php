<?php

/**
 * This file is part of the Tracy (https://tracy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com) Modified by FlyingHail <flyinghail@msn.com>
 */

namespace Hail\Tracy;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;

/**
 * Logger.
 */
class Logger implements LoggerInterface
{
    use LoggerTrait;

    /** @var string name of the directory where errors should be logged */
    public $directory;

    /** @var string|array email or emails to which send error notifications */
    public $email;

    /** @var string sender of email notifications */
    public $fromEmail;

    /** @var mixed interval for sending email is 2 days */
    public $emailSnooze = '2 days';

    /** @var callable handler for sending emails */
    public $mailer;

    /** @var BlueScreen */
    private $blueScreen;


    public function __construct($directory, $email = null, BlueScreen $blueScreen = null)
    {
        $this->directory = $directory;
        $this->email = $email;
        $this->blueScreen = $blueScreen;
        $this->mailer = [$this, 'defaultMailer'];
    }

    /**
     * Logs message or exception to file and sends email notification.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return null|string logged error filename
     */
    public function log($level, $message, array $context = [])
    {
        if (!$this->directory) {
            throw new \LogicException('Directory is not specified.');
        }

        if (!is_dir($this->directory)) {
            throw new \RuntimeException("Directory '$this->directory' is not found or is not directory.");
        }

        $message = Dumper::interpolate($message, $context);

        $exceptionFile = null;
        if (isset($context['exception'])) {
            // NOTE: per PSR-3, this reserved key could be anything, but if it is an Exception, we
            //       can use that Exception to obtain a stack-trace for output in ChromeLogger.
            $exception = $context['exception'];

            if ($exception instanceof \Throwable) {
                $exceptionFile = $this->getExceptionFile($exception);
            }
        }

        $line = $this->formatLogLine($message, $exceptionFile);
        $file = $this->directory . '/' . strtolower($level ?: LogLevel::DEBUG) . '.log';

        if (!@file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX)) { // @ is escalated to exception
            throw new \RuntimeException("Unable to write to log file '$file'. Is directory writable?");
        }

        if ($exceptionFile) {
            $this->logException($message, $exceptionFile);
        }

        if (in_array($level, [LogLevel::ERROR, LogLevel::EMERGENCY, LogLevel::CRITICAL, LogLevel::ALERT], true)) {
            $this->sendEmail($message);
        }

        return $exceptionFile;
    }


    /**
     * @param  string|\Exception|\Throwable $message
     * @param  string|null                  $exceptionFile
     *
     * @return string
     */
    protected function formatLogLine($message, $exceptionFile = null)
    {
        return implode(' ', [
            @date('[Y-m-d H-i-s]'), // @ timezone may not be set
            preg_replace('#\s*\r?\n\s*#', ' ', Dumper::formatMessage($message)),
            ' @  ' . Helpers::getSource(),
            $exceptionFile ? ' @@  ' . basename($exceptionFile) : null,
        ]);
    }


    /**
     * @param  \Throwable
     *
     * @return string
     */
    public function getExceptionFile(\Throwable $exception)
    {
        $data = [];
        while ($exception) {
            $data[] = [
                $exception->getMessage(),
                $exception->getCode(),
                $exception->getFile(),
                $exception->getLine(),
                array_map(function ($item) {
                    unset($item['args']);

                    return $item;
                }, $exception->getTrace()),
            ];
            $exception = $exception->getPrevious();
        }

        $hash = substr(md5(serialize($data)), 0, 10);
        $dir = strtr($this->directory . '/', '\\/', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR);
        foreach (new \DirectoryIterator($this->directory) as $file) {
            if (strpos($file->getBasename(), $hash)) {
                return $dir . $file;
            }
        }

        return $dir . 'exception--' . @date('Y-m-d--H-i') . "--$hash.html"; // @ timezone may not be set
    }


    /**
     * Logs exception to the file if file doesn't exist.
     *
     * @param  \Exception|\Throwable
     *
     * @return string logged error filename
     */
    protected function logException($exception, $file = null)
    {
        $file = $file ?: $this->getExceptionFile($exception);
        $bs = $this->blueScreen ?: new BlueScreen;
        $bs->renderToFile($exception, $file);

        return $file;
    }


    /**
     * @param  string|\Exception|\Throwable
     *
     * @return void
     */
    protected function sendEmail($message)
    {
        $snooze = is_numeric($this->emailSnooze)
            ? $this->emailSnooze
            : @strtotime($this->emailSnooze) - time(); // @ timezone may not be set

        if ($this->email && $this->mailer
            && @filemtime($this->directory . '/email-sent') + $snooze < time() // @ file may not exist
            && @file_put_contents($this->directory . '/email-sent', 'sent') // @ file may not be writable
        ) {
            call_user_func($this->mailer, $message, implode(', ', (array) $this->email));
        }
    }


    /**
     * Default mailer.
     *
     * @param  string|\Exception|\Throwable
     * @param  string
     *
     * @return void
     * @internal
     */
    public function defaultMailer($message, $email)
    {
        $host = preg_replace('#[^\w.-]+#', '', $_SERVER['HTTP_HOST'] ?? php_uname('n'));
        $parts = str_replace(
            ["\r\n", "\n"],
            ["\n", PHP_EOL],
            [
                'headers' => implode("\n", [
                        'From: ' . ($this->fromEmail ?: "noreply@$host"),
                        'X-Mailer: Tracy',
                        'Content-Type: text/plain; charset=UTF-8',
                        'Content-Transfer-Encoding: 8bit',
                    ]) . "\n",
                'subject' => "PHP: An error occurred on the server $host",
                'body' => Dumper::formatMessage($message) . "\n\nsource: " . Helpers::getSource(),
            ]
        );

        mail($email, $parts['subject'], $parts['body'], $parts['headers']);
    }
}
