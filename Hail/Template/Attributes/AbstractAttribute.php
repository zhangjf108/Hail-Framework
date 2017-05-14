<?php

namespace Hail\Template\Attributes;

use Hail\Template\VueResolver;

/**
 * Class AbstractAttribute
 * @package Hail\Template\Attributes
 *
 * @property-read string $name
 */
abstract class AbstractAttribute
{
    const name = '';

    /**
     * @var VueResolver
     */
    public $resolver;

    public function __construct()
    {
        $this->resolver = new VueResolver();
    }

    public function __get($name)
    {
        if ($name === 'name') {
            return $this->name = static::name;
        }

        throw new \InvalidArgumentException('Property not defined: ' . $name);
    }

    abstract public function process(\DOMElement $element, $expression);

    public function resolveExpression($expression)
    {
        return $this->resolver->resolve($expression);
    }

    /**
     * insert a php code before an element.
     *
     * @param \DOMElement $element
     * @param             $phpExpression
     */
    public function before(\DOMElement $element, $phpExpression)
    {
        $exp = new \DOMProcessingInstruction('php', $phpExpression . ' ?');
        $newLine = new \DOMText("\r\n");
        $element->parentNode->insertBefore($exp, $element);
        $element->parentNode->insertBefore($newLine, $element);
    }

    /**
     * insert a php code after an element.
     *
     * @param \DOMElement $element
     * @param             $phpExpression
     */
    public function after(\DOMElement $element, $phpExpression)
    {
        $exp = new \DOMProcessingInstruction('php', $phpExpression . ' ?');
        $newLine = new \DOMText("\r\n");

        if ($element->nextSibling) {
            $element->parentNode->insertBefore($newLine, $element->nextSibling);
            $element->parentNode->insertBefore($exp, $element->nextSibling);
        } else {
            $element->parentNode->appendChild($newLine);
            $element->parentNode->appendChild($exp);
        }
    }

    /**
     * set inner text of the an element.
     *
     * @param \DOMElement $element
     * @param             $phpExpression
     */
    public function text(\DOMElement $element, $phpExpression)
    {
        while ($element->childNodes->length) {
            $element->removeChild($element->firstChild);
        }
        if ($phpExpression) {
            $exp = new \DOMProcessingInstruction('php', $phpExpression . ' ?');
            $element->appendChild($exp);
        }

    }

    /**
     * Replace entire element.
     *
     * @param \DOMElement $element
     * @param string $phpExpression
     */
    public function replace(\DOMElement $element, string $phpExpression = '')
    {
        $phpExpression = trim($phpExpression);
        if ($phpExpression) {
            $this->before($element, $phpExpression);
        }
    }

    public function addStyle(\DOMElement $element, string $expression)
    {
        $expression = trim($expression);
        if ($expression) {
            $style = rtrim($element->getAttribute('style'), ';') . '; ';
            $element->setAttribute('style', $style . $expression);
        }
    }
}