<?php

namespace Hail\Template\Processor;

class VueIf extends AbstractProcessor
{
    public function attribute(): string
    {
        return 'v-if';
    }

    public function process(\DOMElement $element, $expression)
    {
        $expression = $this->resolveExpression($expression);

        $startCode = 'if (' . $expression . ') { ';
        $endCode = '} ';

        $this->before($element, $startCode);
        $this->after($element, $endCode);
    }
}