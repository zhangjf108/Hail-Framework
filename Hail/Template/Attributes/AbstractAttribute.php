<?php

namespace Hail\Template\Attributes;

use Hail\Template\Resolvers\SyntaxResolver;

abstract class AbstractAttribute
{
    public $name;

    /**
     * @var SyntaxResolver
     */
    public $resolver;

    public function __construct(SyntaxResolver $resolver)
    {
        $this->resolver = $resolver;
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

    /**
     * Extract attributes expressions. for tal:attributes, tal:define
     *
     * @param $str
     *
     * @return array
     */
    protected function splitExpression($str)
    {
        $value = trim($str);
        $pos = strpos($value, ' ');
        $var1 = substr($value, 0, $pos);
        $var2 = substr($value, $pos);

        return [
            $var1,
            $var2,
        ];
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