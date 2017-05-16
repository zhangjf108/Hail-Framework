<?php

namespace Hail\Template;

use LogicException;
use Throwable;

/**
 * Container which holds template data and provides access to template functions.
 */
class Template
{
    /**
     * Instance of the template engine.
     *
     * @var Engine
     */
    protected $engine;

    /**
     * The file path of the template.
     *
     * @var string
     */
    protected $template;

    /**
     * The data assigned to the template.
     *
     * @var array
     */
    protected $data = [];

    /**
     * An array of section content.
     *
     * @var array
     */
    protected $sections = [];

    /**
     * The name of the section currently being rendered.
     *
     * @var string
     */
    protected $sectionName;

    /**
     * Whether the section should be appended or not.
     *
     * @var boolean
     */
    protected $appendSection;

    /**
     * The name of the template layout.
     *
     * @var string
     */
    protected $layoutName;

    /**
     * The data assigned to the template layout.
     *
     * @var array
     */
    protected $layoutData;

    /**
     * Create new Template instance.
     *
     * @param Engine $engine
     * @param string $name
     * @param string $file
     */
    public function __construct(Engine $engine, string $name, string $file)
    {
        $this->engine = $engine;
        $this->template = $file;

        $this->data($this->engine->getData($name));
    }

    /**
     * Magic method used to call extension functions.
     *
     * @param  string $name
     * @param  array  $arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->engine->callFunction($name, $this, $arguments);
    }

    /**
     * Assign or get template data.
     *
     * @param  array $data
     *
     * @return mixed
     */
    public function data(array $data = null)
    {
        if (null === $data) {
            return $this->data;
        }

        $this->data = array_merge($this->data, $data);
    }


    /**
     * Render the template and layout.
     *
     * @param array $data
     *
     * @return string
     * @throws \Throwable
     */
    public function render(array $data = [])
    {
        $this->data($data);
        unset($data);
        extract($this->data, EXTR_OVERWRITE);

        $level = ob_get_level();

        try {
            ob_start();
            include $this->template;
            $content = ob_get_clean();

            if (null !== $this->layoutName) {
                $layout = $this->engine->make($this->layoutName);
                $layout->sections = array_merge($this->sections, ['content' => $content]);
                $content = $layout->render($this->layoutData);
            }

            return $content;
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw $e;
        }
    }

    /**
     * Set the template's layout.
     *
     * @param  string $name
     * @param  array  $data
     *
     * @return null
     */
    public function layout($name, array $data = [])
    {
        $this->layoutName = $name;
        $this->layoutData = $data;
    }

    /**
     * Start a new section block.
     *
     * @param  string $name
     *
     * @return null
     */
    public function start($name)
    {
        if ($name === 'content') {
            throw new LogicException('The section name "content" is reserved.');
        }

        if ($this->sectionName) {
            throw new LogicException('You cannot nest sections within other sections.');
        }

        $this->sectionName = $name;

        ob_start();
    }

    /**
     * Start a new append section block.
     *
     * @param  string $name
     *
     * @return null
     */
    public function push($name)
    {
        $this->appendSection = true;

        $this->start($name);
    }

    /**
     * Stop the current section block.
     */
    public function stop()
    {
        if (null === $this->sectionName) {
            throw new LogicException('You must start a section before you can stop it.');
        }

        if (!isset($this->sections[$this->sectionName])) {
            $this->sections[$this->sectionName] = '';
        }

        $this->sections[$this->sectionName] = $this->appendSection ? $this->sections[$this->sectionName] . ob_get_clean() : ob_get_clean();
        $this->sectionName = null;
        $this->appendSection = false;
    }

    /**
     * Alias of stop().
     */
    public function end()
    {
        $this->stop();
    }

    /**
     * Returns the content for a section block.
     *
     * @param  string $name    Section name
     * @param  string $default Default section content
     *
     * @return string|null
     */
    public function section($name, $default = null)
    {
        if (!isset($this->sections[$name])) {
            return $default;
        }

        return $this->sections[$name];
    }

    /**
     * Fetch a rendered template.
     *
     * @param  string $name
     * @param  array  $data
     *
     * @return string
     */
    public function fetch($name, array $data = [])
    {
        return $this->engine->capture($name, $data);
    }

    /**
     * Output a rendered template.
     *
     * @param  string $name
     * @param  array  $data
     */
    public function insert($name, array $data = [])
    {
        $this->engine->render($name, $data);
    }

    /**
     * Apply multiple functions to variable.
     *
     * @param  mixed  $var
     * @param  string $functions
     *
     * @return mixed
     */
    public function batch($var, $functions)
    {
        foreach (explode('|', $functions) as $function) {
            if ($this->engine->doesFunctionExist($function)) {
                $var = $this->$function($var);
            } elseif (is_callable($function)) {
                $var = $function($var);
            } else {
                throw new LogicException('The batch function could not find the "' . $function . '" function.');
            }
        }

        return $var;
    }

    /**
     * Escape string.
     *
     * @param  string      $string
     * @param  null|string $functions
     *
     * @return string
     */
    public function escape($string, $functions = null)
    {
        static $flags;

        if (null === $flags) {
            $flags = ENT_QUOTES | (defined('ENT_SUBSTITUTE') ? ENT_SUBSTITUTE : 0);
        }

        if ($functions) {
            $string = $this->batch($string, $functions);
        }

        return htmlspecialchars($string, $flags, 'UTF-8');
    }

    /**
     * Alias to escape function.
     *
     * @param  string      $string
     * @param  null|string $functions
     *
     * @return string
     */
    public function e($string, $functions = null)
    {
        return $this->escape($string, $functions);
    }
}
