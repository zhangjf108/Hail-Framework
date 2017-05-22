<?php

namespace Hail\Template;

use Hail\Template\Extension\ExtensionInterface;
use Hail\Template\Processor\ProcessorInterface;

class Engine
{
    public $defaultProcessors = [
        Processor\VueFor::class,
        Processor\VueShow::class,
        Processor\VueIf::class,
        Processor\VueElseIf::class,
        Processor\VueElse::class,
        Processor\VueText::class,
        Processor\VueHtml::class,
        Processor\VueBind::class,
    ];

    /**
     * @var ProcessorInterface[]
     */
    protected $processors = [];

    /**
     * @var string
     */
    protected $directory;

    /**
     * @var string
     */
    protected $fallback;

    /**
     * @var string
     */
    protected $cacheDirectory;

    /**
     * Collection of template functions.
     *
     * @var callable[]
     */
    protected $functions;

    public function __construct(array $config = [])
    {
        if (!isset($config['directory'], $config['fallback'])) {
            throw new \LogicException('Path to template directory is not set.');
        }

        if (!isset($config['cache'])) {
            throw new \LogicException('Path to temporary directory is not set.');
        }

        $this->directory = $this->setDirectory($config['directory'] ?? null);
        $this->fallback = $this->setFallback($config['fallback'] ?? null);
        $this->cacheDirectory = $this->setCacheDirectory($config['cache']);

        $this->data = new Data();
    }

    /**
     * Add preassigned template data.
     * @param  array             $data;
     * @param  null|string|array $templates;
     * @return Engine
     */
    public function addData(array $data, $templates = null)
    {
        $this->data->add($data, $templates);
        return $this;
    }

    /**
     * Get all preassigned template data.
     * @param  null|string $template;
     * @return array
     */
    public function getData($template = null)
    {
        return $this->data->get($template);
    }

    /**
     * Set path to templates directory.
     *
     * @param  string $directory
     *
     * @return self
     */
    public function setDirectory($directory)
    {
        $this->directory = $this->normalizeDirectory($directory);

        return $this;
    }

    /**
     * Set path to fallback directory.
     *
     * @param  string $fallback
     *
     * @return self
     */
    public function setFallback($fallback)
    {
        $this->fallback = $this->normalizeDirectory($fallback);

        return $this;
    }

    /**
     * Set path to templates cache directory.
     *
     * @param  string $directory
     *
     * @return self
     */
    public function setCacheDirectory($directory)
    {
        $this->cacheDirectory = $this->normalizeDirectory($directory);

        return $this;
    }

    /**
     * Register a new template function.
     *
     * @param string   $name
     * @param callback $callback
     *
     * @return Engine
     */
    public function registerFunction(string $name, callable $callback)
    {
        if (preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $name) !== 1) {
            throw new \LogicException('Not a valid function name.');
        }

        $this->functions[$name] = $callback;

        return $this;
    }

    /**
     * Remove a template function.
     *
     * @param  string $name
     *
     * @return Engine
     */
    public function dropFunction($name)
    {
        unset($this->functions[$name]);

        return $this;
    }

    /**
     * Get a template function.
     *
     * @param  string $name
     *
     * @return callable
     */
    public function getFunction($name)
    {
        if (!isset($this->functions[$name])) {
            throw new \LogicException('The template function "' . $name . '" was not found.');
        }

        return $this->functions[$name];
    }

    /**
     * Call the function.
     *
     * @param string $name
     * @param old    $template
     * @param array  $arguments
     *
     * @return mixed
     */
    public function callFunction($name, old $template = null, $arguments = [])
    {
        $callable = $this->getFunction($name);

        if (is_array($callable) and
            isset($callable[0]) and
            $callable[0] instanceof ExtensionInterface
        ) {
            $callable[0]->template = $template;
        }

        return $callable(...$arguments);
    }

    /**
     * Check if a template function exists.
     *
     * @param  string $name
     *
     * @return boolean
     */
    public function doesFunctionExist($name)
    {
        return isset($this->functions[$name]);
    }

    /**
     * Load an extension.
     *
     * @param  ExtensionInterface $extension
     *
     * @return Engine
     */
    public function loadExtension(ExtensionInterface $extension)
    {
        $extension->register($this);

        return $this;
    }

    /**
     * Load multiple extensions.
     *
     * @param  array $extensions
     *
     * @return Engine
     */
    public function loadExtensions(array $extensions = [])
    {
        foreach ($extensions as $extension) {
            $this->loadExtension($extension);
        }

        return $this;
    }

    /**
     * add processor class.
     *
     * @param ProcessorInterface $processor
     *
     * @return self
     */
    public function addProcessor(ProcessorInterface $processor)
    {
        if ($this->processors === []) {
            $this->defaultProcessors[] = $processor;
        } else {
            $this->processors[$processor->attribute()] = $processor;
        }

        return $this;
    }

    protected function initProcessors()
    {
        foreach ($this->defaultProcessors as $processor) {
            if (!$processor instanceof ProcessorInterface) {
                $processor = new $processor();
            }

            $this->processors[$processor->attribute()] = $processor;
        }
    }

    /**
     * Compile the html, and return the compiled file.
     *
     * @return string the compiled file.
     * @throws \LogicException
     */
    public function compile($name)
    {
        [
            'template' => $template,
            'cache' => $cache,
        ] = $this->getTemplateFile($name);

        if (filemtime($template) < filemtime($cache)) {
            return $cache;
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $dom->loadHTMLFile($template);

        if ($this->processors === []) {
            $this->initProcessors();
        }

        $this->parseElement($dom);
        $this->clearAttributes($dom);

        $content = $dom->saveHTML();

        $replacedContent = preg_replace_callback('/\&lt\;\?php.+\?\&gt\;/', ['static', 'decodePhpCode'], $content);

        file_put_contents($cache, $replacedContent);

        return $cache;
    }

    protected static function decodePhpCode($match)
    {
        return urldecode(htmlspecialchars_decode($match[0]));
    }

    protected function getTemplateFile(string $name)
    {
        foreach ([$this->directory, $this->fallback] as $dir) {
            if (empty($dir)) {
                continue;
            }

            $file = $this->getFile($dir . $name);
            if ($file !== null) {
                return [
                    'template' => $file,
                    'cache' => $this->convertToCache($file, $dir),
                ];
            }
        }

        throw new \LogicException('Template file not found:' . $name);
    }

    protected function convertToCache(string $template, string $dir)
    {
        $file = str_replace($dir, $this->cacheDirectory, $template);
        if (is_file($file)) {
            return $file;
        }

        $dir = dirname($file);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new \RuntimeException('Cache directory not exists: ' . str_replace($this->cacheDirectory, '',
                        $dir));
            }
        }

        return $file;
    }

    protected function getFile($file)
    {
        if (is_dir($file)) {
            $file .= '/index.php';
        } elseif (strrchr($file, '.') !== '.php') {
            $file .= '.php';
        }

        if (is_file($file)) {
            return $file;
        }

        return null;
    }

    /**
     * @param \DOMNode $element
     *
     * @throws \LogicException
     */
    protected function parseElement(\DOMNode $element, $process = false): void
    {
        $template = false;

        if ($element instanceof \DOMElement && $element->hasAttributes()) {
            $process = $process ?: $element->hasAttribute('v-php');

            if ($process) {
                $vueCopy = $element->hasAttribute('v-once');
                $template = $element->tagName === 'template';

                foreach ($this->processors as $attr => $processor) {
                    if (!$element->hasAttribute($attr)) {
                        continue;
                    }

                    if (!$vueCopy) {
                        switch ($attr) {
                            case 'v-if':
                                $elements = $this->vueIfElements($element);
                                $end = end($elements);

                                foreach ($elements as $v) {
                                    $forVue = clone $v;
                                    $forVue->removeAttribute('v-php');
                                    $this->insertAfter($end, $forVue);
                                }
                                break;

                            case 'v-else':
                            case 'v-else-if':
                                break;

                            default:
                                $forVue = clone $element;
                                $forVue->removeAttribute('v-php');
                                $this->insertAfter($element, $forVue);
                        }

                        $vueCopy = true;
                    }

                    if ($attr === 'v-bind') {
                        $processor->process($element, null);
                    } elseif ($processor->process($element, $element->getAttribute($attr))) {
                        $this->remove($element);

                        return;
                    }
                }
            }
        }

        if ($element->hasChildNodes()) {
            foreach ($element->childNodes as $childNode) {
                $this->parseElement($childNode, $process);

                if ($template) {
                    if (!$childNode instanceof \DOMElement) {
                        throw new \LogicException('template children must be a dom element');
                    }

                    if (!$process) {
                        $childNode->setAttribute('v-php', '');
                    }

                    $this->insertAfter($element, $childNode);
                }
            }
        }

        if ($template) {
            $this->remove($element);
        }
    }

    protected function insertAfter(\DOMNode $element, $new)
    {
        if ($element->nextSibling) {
            $element->parentNode->insertBefore($new, $element->nextSibling);
        } else {
            $element->parentNode->appendChild($new);
        }
    }

    protected function remove(\DOMNode $element)
    {
        $element->parentNode->removeChild($element);
    }

    /**
     * 解析过程中可能需要判断上下文，所以在解析完成后再清理 attribute
     *
     * @param \DOMNode $element
     */
    protected function clearAttributes(\DOMNode $element, $process = false): void
    {
        if ($element instanceof \DOMElement && $element->hasAttributes()) {
            $process = $process ?: $element->hasAttribute('v-php');

            if ($process) {
                $element->removeAttribute('v-php');

                foreach ($this->processors as $attr => $processor) {
                    if (!$element->hasAttribute($attr)) {
                        continue;
                    }

                    $element->removeAttribute($attr);
                }

                if ($element->hasAttribute('v-once')) {
                    $element->removeAttribute('v-once');
                } else {
                    $element->setAttribute('v-if', 'false');
                }
            }
        }

        if ($element->hasChildNodes()) {
            foreach ($element->childNodes as $childNode) {
                $this->clearAttributes($childNode, $process);
            }
        }
    }

    /**
     * @param \DOMElement $element
     *
     * @return \DOMElement[]
     */
    protected function vueIfElements(\DOMElement $element)
    {
        $element = $element->nextSibling;
        if (
            $element->hasAttribute('v-php') && (
                $element->hasAttribute('v-else') ||
                $element->hasAttribute('v-else-if')
            )
        ) {
            $elements = $this->vueIfElements($element);
            array_unshift($elements, $element);

            return $elements;
        }

        return [];
    }

    protected function normalizeDirectory(?string $dir): ?string
    {
        if ($dir !== null) {
            if (strpos($dir, '\\') !== false) {
                $dir = str_replace('\\', '/', $dir);
            }

            $dir = rtrim($dir, '/') . '/';
        }

        return $dir;
    }

    /**
     * Render the compiled php code by data.
     *
     * @param string $name
     * @param array  $params
     */
    public function render(string $name, array $params = [])
    {
        echo $this->capture($name, $params);
    }


    /**
     * @param       $name
     * @param array $params
     *
     * @return string
     */
    public function capture($name, array $params)
    {
        return $this->make($name)->render($params);
    }

    /**
     * @param $name
     *
     * @return Template
     */
    public function make($name)
    {
        $name = trim($name, '/\\');
        $file = $this->compile($name);

        return new Template($this, $name, $file);
    }
}