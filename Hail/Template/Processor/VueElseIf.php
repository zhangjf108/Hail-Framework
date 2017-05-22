<?php

namespace Hail\Template\Processor;

class VueElseIf extends AbstractProcessor
{
    public function attribute(): string
    {
        return 'v-else-if';
    }

    public function process(\DOMElement $element, $expression)
    {
        $prev = $element->previousSibling;
        if (!$prev->hasAttribute('v-if')) {
            throw new \LogicException('v-else-if must after v-if');
        }

        $expression = trim($expression);

        $startCode = 'elseif (' . $expression . ') { ';
        $endCode = '} ';

        $this->before($element, $startCode);
        $this->after($element, $endCode);
    }
}