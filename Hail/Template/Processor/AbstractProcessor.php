<?php

namespace Hail\Template\Processor;


/**
 * Class AbstractProcessor
 * @package Hail\Template\Processor
 */
abstract class AbstractProcessor implements ProcessorInterface
{
    /**
     * insert a php code before an element.
     *
     * @param \DOMElement $element
     * @param             $phpExpression
     */
	protected function before(\DOMElement $element, $phpExpression)
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
	protected function after(\DOMElement $element, $phpExpression)
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
	protected function text(\DOMElement $element, $phpExpression)
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
    protected function replace(\DOMElement $element, string $phpExpression = '')
    {
        $phpExpression = trim($phpExpression);
        if ($phpExpression) {
            $this->before($element, $phpExpression);
        }
    }

	protected function addStyle(\DOMElement $element, string $expression)
    {
        $expression = trim($expression);
        if ($expression) {
            $style = rtrim($element->getAttribute('style'), ';') . '; ';
            $element->setAttribute('style', $style . $expression);
        }
    }
}