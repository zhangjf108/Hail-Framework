<?php

namespace Hail\Template\Attributes;

class VueIf extends AbstractAttribute
{
    const name = 'v-if';

    public function process(\DOMElement $element, $expression)
    {
        $expression = $this->resolveExpression($expression);

        $startCode = 'if (' . $expression . ') { ';
        $endCode = '} ';

        $this->before($element, $startCode);
        $this->after($element, $endCode);
    }
}