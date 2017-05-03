<?php

/**
 * This file is part of the Latte (https://latte.nette.org)
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Hail\Latte;

use Hail\Latte\Compiler\{
    Compiler, Parser, PhpHelpers
};
use Hail\Latte\Exception\CompileException;
use Hail\TemplateInterface;
use Psr\Http\Message\ResponseInterface;


/**
 * Templating engine Latte.
 */
class Engine implements TemplateInterface
{
    use Strict;

    const VERSION = '3.0.0-dev';

    /** Content types */
    const CONTENT_HTML = 'html',
        CONTENT_XHTML = 'xhtml',
        CONTENT_XML = 'xml',
        CONTENT_JS = 'js',
        CONTENT_CSS = 'css',
        CONTENT_ICAL = 'ical',
        CONTENT_TEXT = 'text';

    /** @var Parser */
    private $parser;

    /** @var Compiler */
    private $compiler;

    /** @var Runtime\FilterExecutor */
    private $filters;

    /** @var array */
    private $providers = [];

    /** @var string */
    private $contentType = self::CONTENT_HTML;

    /** @var string */
    private $baseDirectory;

    /** @var string */
    private $tempDirectory;

    /** @var bool */
    private $autoRefresh = true;

    /** @var bool */
    private $strictTypes = false;

    /** @var ResponseInterface */
    public $response;


    public function __construct(array $config)
    {
        if (!isset($config['directory'])) {
            throw new \LogicException('Path to template directory is not set.');
        }

        if (!isset($config['cache'])) {
            throw new \LogicException('Path to temporary directory is not set.');
        }

        $this->baseDirectory = rtrim($config['directory'], '/') . '/';
        $this->tempDirectory = rtrim($config['cache'], '/') . '/';

        $this->filters = new Runtime\FilterExecutor;
    }

    /**
     * Renders template to ResponseInterface.
     *
     * @param ResponseInterface $response
     * @param string            $name
     * @param array             $params
     *
     * @return ResponseInterface
     */
    public function renderToResponse(ResponseInterface $response, string $name, array $params = []): ResponseInterface
    {
        $this->response = $response;

        $body = $this->response->getBody();
        $body->write($this->renderToString($name));

        return $this->response;
    }

    /**
     * Renders template to output.
     *
     * @param string $name
     * @param array  $params
     */
    public function render(string $name, array $params = [])
    {
        if (strrchr($name, '.') !== '.latte') {
            $name .= '.latte';
        }

        $this->createTemplate($name, $params)->render();
    }

    /**
     * Renders template to string.
     *
     * @param string $name
     * @param array  $params
     *
     * @return string
     */
    public function renderToString(string $name, array $params = []): string
    {
        if (strrchr($name, '.') !== '.latte') {
            $name .= '.latte';
        }

        return $this->createTemplate($name, $params)->capture();
    }

    /**
     * Creates template object.
     *
     * @param       $name
     * @param array $params
     *
     * @return Runtime\Template
     */
    public function createTemplate($name, array $params = []): Runtime\Template
    {
        $class = $this->getTemplateClass($name);
        if (!class_exists($class, false)) {
            $this->loadTemplate($name);
        }

        return new $class($this, $params, $this->filters, $this->providers, $name);
    }


    /**
     * Compiles template to PHP code.
     *
     * @param string $name
     *
     * @return string
     * @throws CompileException
     */
    public function compile(string $name): string
    {
        $source = $this->getContent($name);

        try {
            $tokens = $this->getParser()->setContentType($this->contentType)
                ->parse($source);

            $code = $this->getCompiler()->setContentType($this->contentType)
                ->compile($tokens, $this->getTemplateClass($name));

        } catch (\Exception $e) {
            if (!$e instanceof CompileException) {
                $e = new CompileException("Thrown exception '{$e->getMessage()}'", null, $e);
            }
            $line = isset($tokens) ? $this->getCompiler()->getLine() : $this->getParser()->getLine();
            throw $e->setSource($source, $line, $name);
        }

        if (!preg_match('#\n|\?#', $name)) {
            $code = "<?php\n// source: $name\n?>" . $code;
        }
        if ($this->strictTypes) {
            $code = "<?php\ndeclare(strict_types=1);\n?>" . $code;
        }
        $code = PhpHelpers::reformatCode($code);

        return $code;
    }


    /**
     * Compiles template to cache.
     *
     * @return void
     * @throws \LogicException
     */
    public function warmupCache(string $name)
    {
        $class = $this->getTemplateClass($name);
        if (!class_exists($class, false)) {
            $this->loadTemplate($name);
        }
    }


    /**
     * @return void
     */
    private function loadTemplate($name)
    {
        $file = $this->getCacheFile($name);

        if ($cacheExists = file_exists($file)) {
            if (!$this->isExpired($file, $name) && (include $file) !== false) {
                return;
            }

            $cacheExists = false;
        } elseif (
            !file_exists($this->tempDirectory) &&
            !@mkdir($this->tempDirectory, 0777, true) && !is_dir($this->tempDirectory)
        ) {
            throw new \LogicException('Temporary directory not exists!');
        }

        $handle = fopen("$file.lock", 'c+');
        if (!$handle || !flock($handle, LOCK_EX)) {
            throw new \RuntimeException("Unable to acquire exclusive lock '$file.lock'.");
        }

        if (!$cacheExists) {
            $code = $this->compile($name);
            if (file_put_contents("$file.tmp", $code) !== strlen($code) || !rename("$file.tmp", $file)) {
                @unlink("$file.tmp"); // @ - file may not exist
                throw new \RuntimeException("Unable to create '$file'.");
            }

            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($file, true);
            }
        }

        if ((include $file) === false) {
            throw new \RuntimeException("Unable to load '$file'.");
        }

        flock($handle, LOCK_UN);
        fclose($handle);
        @unlink("$file.lock"); // @ file may become locked on Windows
    }


    private function isExpired(string $file, string $name): bool
    {
        if (!$this->autoRefresh) {
            return false;
        }

        return ((int) @filemtime($this->baseDirectory . $name)) > ((int) @filemtime($file)); // @ - file may not exist
    }


    public function getCacheFile($name): string
    {
        $hash = substr($this->getTemplateClass($name), 8);
        $base = preg_match('#([/\\\\][\w@.-]{3,35}){1,3}\z#', $name, $m)
            ? preg_replace('#[^\w@.-]+#', '-', substr($m[0], 1)) . '--'
            : '';

        return $this->tempDirectory . $base . $hash . '.php';
    }


    public function getTemplateClass($name): string
    {
        $key = $this->baseDirectory . $name . "\00" . self::VERSION;

        return 'Template' . substr(md5($key), 0, 10);
    }


    /**
     * Registers run-time filter.
     *
     * @param string|NULL $name
     * @param callable    $callback
     *
     * @return static
     */
    public function addFilter(?string $name, callable $callback)
    {
        $this->filters->add($name, $callback);

        return $this;
    }


    /**
     * Returns all run-time filters.
     *
     * @return string[]
     */
    public function getFilters(): array
    {
        return $this->filters->getAll();
    }


    /**
     * Call a run-time filter.
     *
     * @param string $name filter name
     * @param array  $args arguments
     *
     * @return mixed
     */
    public function invokeFilter(string $name, array $args)
    {
        return ($this->filters->$name)(...$args);
    }


    /**
     * Adds new macro.
     *
     * @return static
     */
    public function addMacro($name, MacroInterface $macro)
    {
        $this->getCompiler()->addMacro($name, $macro);

        return $this;
    }


    /**
     * Adds new provider.
     *
     * @return static
     */
    public function addProvider($name, $value)
    {
        $this->providers[$name] = $value;

        return $this;
    }


    /**
     * Returns all providers.
     */
    public function getProviders(): array
    {
        return $this->providers;
    }


    /**
     * @return static
     */
    public function setContentType($type)
    {
        $this->contentType = $type;

        return $this;
    }


    /**
     * Sets path to temporary directory.
     *
     * @return static
     */
    public function setTempDirectory($path)
    {
        $this->tempDirectory = $path;

        return $this;
    }


    /**
     * Sets auto-refresh mode.
     *
     * @param bool $on
     *
     * @return static
     */
    public function setAutoRefresh(bool $on = true)
    {
        $this->autoRefresh = $on;

        return $this;
    }


    /**
     * Enables declare(strict_types=1) in templates.
     *
     * @param bool $on
     *
     * @return static
     */
    public function setStrictTypes(bool $on = true)
    {
        $this->strictTypes = $on;

        return $this;
    }


    public function getParser(): Parser
    {
        if (!$this->parser) {
            $this->parser = new Parser;
        }

        return $this->parser;
    }


    public function getCompiler(): Compiler
    {
        if (!$this->compiler) {
            $this->compiler = new Compiler;
            Macros\CoreMacros::install($this->compiler);
            Macros\BlockMacros::install($this->compiler);
        }

        return $this->compiler;
    }

    /**
     * Returns template source code.
     */
    private function getContent(string $file): string
    {
        $file = $this->baseDirectory . $file;

        if (!is_file($file)) {
            throw new \RuntimeException("Missing template file '$file'.");
        }

        if (filemtime($file) > NOW && @touch($file) === false) {
            trigger_error("File's modification time is in the future. Cannot update it: " . error_get_last()['message'],
                E_USER_WARNING);
        }

        return file_get_contents($file);
    }

    /**
     * Returns referred template name.
     *
     * @param string $file
     * @param string $referringFile
     *
     * @return string
     */
    public function getReferredName(string $file, string $referringFile): string
    {
        return static::normalizePath($referringFile . '/../' . $file);
    }

    private static function normalizePath($path): string
    {
        $res = [];

        if (strpos($path, '\\') !== false) {
            $path = str_replace('\\', '/', $path);
        }

        foreach (explode('/', $path) as $part) {
            if ($part === '..' && $res && end($res) !== '..') {
                array_pop($res);
            } elseif ($part !== '.' && $part !== '') {
                $res[] = $part;
            }
        }

        return implode(DIRECTORY_SEPARATOR, $res);
    }
}
