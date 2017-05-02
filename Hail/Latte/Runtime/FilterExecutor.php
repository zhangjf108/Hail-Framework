<?php

/**
 * This file is part of the Latte (https://latte.nette.org)
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Hail\Latte\Runtime;

use Hail\Latte\Engine;
use Hail\Latte\Helpers;


/**
 * Filter executor.
 */
class FilterExecutor
{
    /** @var array */
    private $_dynamic = [];

    /** @var array [name => [callback, FilterInfo aware] */
    private $_static = [
        'breaklines' => [[Filters::class, 'breaklines'], false],
        'bytes' => [[Filters::class, 'bytes'], false],
        'capitalize' => [[Filters::class, 'capitalize'], false],
        'datastream' => [[Filters::class, 'dataStream'], false],
        'date' => [[Filters::class, 'date'], false],
        'escapecss' => [[Filters::class, 'escapeCss'], false],
        'escapehtml' => [[Filters::class, 'escapeHtml'], false],
        'escapehtmlcomment' => [[Filters::class, 'escapeHtmlComment'], false],
        'escapeical' => [[Filters::class, 'escapeICal'], false],
        'escapejs' => [[Filters::class, 'escapeJs'], false],
        'escapeurl' => ['rawurlencode', false],
        'escapexml' => [[Filters::class, 'escapeXml'], false],
        'firstupper' => [[Filters::class, 'firstUpper'], false],
        'checkurl' => [[Filters::class, 'safeUrl'], false],
        'implode' => ['implode', false],
        'indent' => [[Filters::class, 'indent'], true],
        'length' => [[Filters::class, 'length'], false],
        'lower' => [[Filters::class, 'lower'], false],
        'number' => ['number_format', false],
        'padleft' => [[Filters::class, 'padLeft'], false],
        'padright' => [[Filters::class, 'padRight'], false],
        'repeat' => [[Filters::class, 'repeat'], true],
        'replace' => [[Filters::class, 'replace'], true],
        'replacere' => [[Filters::class, 'replaceRe'], false],
        'strip' => [[Filters::class, 'strip'], true],
        'striphtml' => [[Filters::class, 'stripHtml'], true],
        'striptags' => [[Filters::class, 'stripTags'], true],
        'substr' => [[Filters::class, 'substring'], false],
        'trim' => [[Filters::class, 'trim'], false],
        'truncate' => [[Filters::class, 'truncate'], false],
        'upper' => [[Filters::class, 'upper'], false],
    ];


    /**
     * Registers run-time filter.
     *
     * @param  string|NULL
     *
     * @return static
     */
    public function add($name, callable $callback)
    {
        if ($name == null) { // intentionally ==
            array_unshift($this->_dynamic, $callback);
        } else {
            $name = strtolower($name);
            $this->_static[$name] = [$callback, null];
            unset($this->$name);
        }

        return $this;
    }


    /**
     * Returns all run-time filters.
     * @return string[]
     */
    public function getAll(): array
    {
        return array_combine($tmp = array_keys($this->_static), $tmp);
    }


    /**
     * Returns filter for classic calling.
     */
    public function __get($name): callable
    {
        $lname = strtolower($name);
        if (isset($this->$lname)) { // case mismatch
            return $this->$lname;

        }

        if (isset($this->_static[$lname])) {
            list($callback, $aware) = $this->prepareFilter($lname);
            if ($aware) { // FilterInfo aware filter
                return $this->$lname = function (...$args) use ($callback) {
                    array_unshift($args, $info = new FilterInfo);
                    if ($args[1] instanceof HtmlStringInterface) {
                        $args[1] = $args[1]->__toString();
                        $info->contentType = Engine::CONTENT_HTML;
                    }
                    $res = $callback(...$args);

                    return $info->contentType === Engine::CONTENT_HTML
                        ? new Html($res)
                        : $res;
                };
            }

            // classic filter
            return $this->$lname = $callback;
        }

        return $this->$lname = function (...$args) use ($lname, $name) { // dynamic filter
            array_unshift($args, $lname);
            foreach ($this->_dynamic as $filter) {
                $res = $filter(...$args);
                if ($res !== null) {
                    return $res;
                }

                if (isset($this->_static[$lname])) { // dynamic converted to classic
                    $this->$name = \Closure::fromCallable($this->_static[$lname][0]);

                    return ($this->$name)(...$args);
                }
            }
            $hint = ($t = Helpers::getSuggestion(array_keys($this->_static), $name)) ? ", did you mean '$t'?" : '.';
            throw new \LogicException("Filter '$name' is not defined$hint");
        };
    }


    /**
     * Calls filter with FilterInfo.
     * @return mixed
     */
    public function filterContent($name, FilterInfo $info, ...$args)
    {
        $lname = strtolower($name);
        if (!isset($this->_static[$lname])) {
            $hint = ($t = Helpers::getSuggestion(array_keys($this->_static), $name)) ? ", did you mean '$t'?" : '.';
            throw new \LogicException("Filter |$name is not defined$hint");
        }

        list($callback, $aware) = $this->prepareFilter($lname);
        if ($aware) { // FilterInfo aware filter
            array_unshift($args, $info);

            return $callback(...$args);

        } else { // classic filter
            if ($info->contentType !== Engine::CONTENT_TEXT) {
                trigger_error("Filter |$name is called with incompatible content type " . strtoupper($info->contentType)
                    . ($info->contentType === Engine::CONTENT_HTML ? ', try to prepend |stripHtml.' : '.'),
                    E_USER_WARNING);
            }
            $res = ($this->$name)(...$args);
            if ($res instanceof HtmlStringInterface) {
                trigger_error("Filter |$name should be changed to content-aware filter.");
                $info->contentType = Engine::CONTENT_HTML;
                $res = $res->__toString();
            }

            return $res;
        }
    }


    private function prepareFilter($name)
    {
        if (!isset($this->_static[$name][1])) {
            $callback = \Closure::fromCallable($this->_static[$name][0]);
            $ref = new \ReflectionFunction($callback);

            $this->_static[$name][1] = ($tmp = $ref->getParameters())
                && (string) $tmp[0]->getType() === FilterInfo::class;
        }

        return $this->_static[$name];
    }

}
