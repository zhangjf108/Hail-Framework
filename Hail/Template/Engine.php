<?php

namespace Hail\Template;

use Hail\Template\Resolvers\DefaultResolver;
use Hail\Template\Resolvers\SyntaxResolver;

class Engine
{
    public $defaultProcessors = [
        Attributes\VueFor::class,
        Attributes\VueShow::class,
        Attributes\VueIf::class,
        Attributes\VueElseIf::class,
        Attributes\VueElse::class,
        Attributes\VueHtml::class,
        Attributes\VueBind::class,
        Attributes\VueContent::class,
        Attributes\VueDefine::class,
        Attributes\VueReplace::class,
    ];

    protected $resolver;
    protected $processors = [];
    protected $template;

    protected $baseDirectory;
    protected $cacheDirectory;

    public function __construct()
    {
        if (!isset($config['directory'])) {
            throw new \LogicException('Path to template directory is not set.');
        }

        if (!isset($config['cache'])) {
            throw new \LogicException('Path to temporary directory is not set.');
        }

        $this->baseDirectory = rtrim($config['directory'], '/') . '/';
        $this->cacheDirectory = rtrim($config['cache'], '/') . '/';

        $this->template = new Template();
    }

    /**
     * Set Processors.
     *
     * @param array $processors
     */
    public function setProcessors($processors = [])
    {
        $this->processors = $processors;
    }

    /**
     * Set resolver.
     *
     * @param SyntaxResolver $resolver
     */
    public function setResolver(SyntaxResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Compile the html, and return the compiled file.
     *
     * @return string the compiled file.
     * @throws \LogicException
     */
    public function compile($name)
    {
        $template = $this->getTemplateFile($name);
        $cache = $this->getCacheFile($name);

        if (filemtime($template) < filemtime($cache)) {
            return $cache;
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $dom->loadHTMLFile($template);

        if ($this->resolver === null) {
            $this->resolver = new DefaultResolver();
        }

        if ($this->processors === []) {
            foreach ($this->defaultProcessors as $processorClass) {
                $processor = new $processorClass($this->resolver);
                $this->processors[$processor->name] = $processor;
            }
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

    protected function getCacheFile($name)
    {
        $file = $this->cacheDirectory . $name . '.php';
        if (file_exists($file)) {
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

    protected function getTemplateFile($name)
    {
        $file = $this->baseDirectory . $name;
        if (!file_exists($file)) {
            throw new \LogicException('Template file not found:' . $name);
        }

        return $file;
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

                $vueBind = [];
                foreach ($element->attributes as $attribute) {
                    $attr = $attribute->nodeName;
                    if (
                        strpos($attr, 'v-bind:') === 0 ||
                        strpos($attr, ':') === 0
                    ) {
                        $attr = explode(':', $attr)[1];
                        $vueBind[] = $attr . '=' . $attribute->nodeValue;
                    }
                }


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
                        $processor->process($element, $vueBind);
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

                foreach ($this->processors as $processor) {
                    if (!$element->hasAttribute($processor->name)) {
                        continue;
                    }

                    $element->removeAttribute($processor->name);
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

    /**
     * Render the compiled php code by data.
     *
     * @param string $name
     * @param array  $params
     */
    public function render(string $name, array $params = [])
    {
        $name = ltrim($name, '/\\');
        if (strrchr($name, '.') !== '.vue') {
            $name .= '.vue';
        }

        $file = $this->compile($name);

        $this->template->render($file, $params);
    }


    public function capture($file, array $params)
    {
        ob_start();
        $this->render($file, $params);
        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }

}