<?php

/**
 * This file is part of the Latte (https://latte.nette.org)
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Hail\Latte\Runtime;

use Hail\Latte\{
    Strict, Engine, Helpers
};


/**
 * Template.
 */
class Template
{
    use Strict;

    /** @var Engine */
    private $engine;

    /** @var string */
    private $name;

    /** @var string  @internal */
    protected $contentType = Engine::CONTENT_HTML;

    /** @var array  @internal */
    protected $params = [];

    /** @var FilterExecutor */
    protected $filters;

    /** @var array [name => method]  @internal */
    protected $blocks = [];

    /** @var string|NULL|FALSE  @internal */
    protected $parentName;

    /** @var Template|NULL  @internal */
    private $referringTemplate;

    /** @var string|NULL  @internal */
    private $referenceType;

    /** @var \stdClass global accumulators for intermediate results */
    public $global;

    /** @var [name => [callbacks]]  @internal */
    protected $blockQueue = [];

    /** @var [name => type]  @internal */
    protected $blockTypes = [];


    public function __construct(Engine $engine, array $params, FilterExecutor $filters, $name)
    {
        $this->engine = $engine;
        $this->params = $params;
        $this->filters = $filters;
        $this->name = $name;
        $this->global = (object) [];
        foreach ($this->blocks as $nm => $method) {
            $this->blockQueue[$nm][] = [$this, $method];
        }
    }


    public function getEngine(): Engine
    {
        return $this->engine;
    }


    public function getName(): string
    {
        return $this->name;
    }


    /**
     * Returns array of all parameters.
     */
    public function getParameters(): array
    {
        return $this->params;
    }


    /**
     * Returns parameter.
     * @return mixed
     */
    public function getParameter($name)
    {
        if (!array_key_exists($name, $this->params)) {
            trigger_error("The variable '$name' does not exist in template.", E_USER_NOTICE);
        }

        return $this->params[$name];
    }


    public function getContentType(): string
    {
        return $this->contentType;
    }


    /**
     * @return string|NULL
     */
    public function getParentName()
    {
        return $this->parentName ?: null;
    }


    /**
     * @return Template|NULL
     */
    public function getReferringTemplate()
    {
        return $this->referringTemplate;
    }


    /**
     * @return string|NULL
     */
    public function getReferenceType()
    {
        return $this->referenceType;
    }


    /**
     * Renders template.
     * @return void
     * @internal
     */
    public function render()
    {
        $this->prepare();

        Filters::$xhtml = (bool) preg_match('#xml|xhtml#', $this->contentType);

        if ($this->referenceType === 'import') {
            if ($this->parentName) {
                $this->createTemplate($this->parentName, [], 'import')->render();
            }

            return;
        }

        if ($this->parentName) { // extends
            ob_start();
            $params = $this->main();
            ob_end_clean();
            $this->createTemplate($this->parentName, $params, 'extends')->render();

            return;
        }

        $this->main();
    }


    /**
     * Renders template.
     * @internal
     */
    protected function createTemplate($name, array $params, $referenceType): Template
    {
        $name = $this->engine->getReferredName($name, $this->name);
        $child = $this->engine->createTemplate($name, $params);
        $child->referringTemplate = $this;
        $child->referenceType = $referenceType;
        $child->global = $this->global;
        if (in_array($referenceType, ['extends', 'includeblock', 'import'], true)) {
            $this->blockQueue = array_merge_recursive($this->blockQueue, $child->blockQueue);
            foreach ($child->blockTypes as $nm => $type) {
                $this->checkBlockContentType($type, $nm);
            }
            $child->blockQueue = &$this->blockQueue;
            $child->blockTypes = &$this->blockTypes;
        }

        return $child;
    }


    /**
     * @param  string|\Closure content -type name or modifier closure
     *
     * @return void
     * @internal
     */
    protected function renderToContentType($mod)
    {
        if ($mod instanceof \Closure) {
            echo $mod($this->capture(), $this->contentType);
        } elseif ($mod && $mod !== $this->contentType) {
            if ($filter = Filters::getConvertor($this->contentType, $mod)) {
                echo $filter($this->capture());
            } else {
                trigger_error("Including '$this->name' with content type " . strtoupper($this->contentType) . ' into incompatible type ' . strtoupper($mod) . '.',
                    E_USER_WARNING);
            }
        } else {
            $this->render();
        }
    }


    /**
     * @return void
     * @internal
     */
    public function prepare()
    {
    }

    /**
     * @return array
     * @internal
     */
    public function main()
    {
        return [];
    }


    /********************* blocks ****************d*g**/


    /**
     * Renders block.
     *
     * @param  string|\Closure $mod content-type name or modifier closure
     *
     * @return void
     * @internal
     */
    protected function renderBlock(string $name, array $params, $mod = null)
    {
        if (empty($this->blockQueue[$name])) {
            $hint = null !== $this->blockQueue && ($t = Helpers::getSuggestion(array_keys($this->blockQueue),
                $name)) ? ", did you mean '$t'?" : '.';
            throw new \RuntimeException("Cannot include undefined block '$name'$hint");
        }

        $block = reset($this->blockQueue[$name]);
        if ($mod && $mod !== ($blockType = $this->blockTypes[$name])) {
            if ($filter = (is_string($mod) ? Filters::getConvertor($blockType, $mod) : $mod)) {
                echo $filter($this->capture(function () use ($block, $params) {
                    $block($params);
                }), $blockType);

                return;
            }
            trigger_error("Including block $name with content type " . strtoupper($blockType) . ' into incompatible type ' . strtoupper($mod) . '.',
                E_USER_WARNING);
        }
        $block($params);
    }


    /**
     * Renders parent block.
     * @return void
     * @internal
     */
    protected function renderBlockParent($name, array $params)
    {
        if (empty($this->blockQueue[$name]) || ($block = next($this->blockQueue[$name])) === false) {
            throw new \RuntimeException("Cannot include undefined parent block '$name'.");
        }
        $block($params);
        prev($this->blockQueue[$name]);
    }


    /**
     * @return void
     * @internal
     */
    protected function checkBlockContentType($current, $name)
    {
        $expected = &$this->blockTypes[$name];
        if ($expected === null) {
            $expected = $current;
        } elseif ($expected !== $current) {
            trigger_error("Overridden block $name with content type " . strtoupper($current) . ' by incompatible type ' . strtoupper($expected) . '.',
                E_USER_WARNING);
        }
    }


    /**
     * Captures output to string.
     * @internal
     */
    public function capture(callable $function = null): string
    {
        try {
            ob_start();
            $this->global->coreCaptured = true;

            if ($function === null) {
                $this->render();
            } else {
                $function();
            }

            return ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        } finally {
            $this->global->coreCaptured = false;
        }
    }
}
