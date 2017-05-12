<?php

namespace Hail\Template\Attributes;

class VueElseIf extends AbstractAttribute
{
    public $name = 'v-else-if';

    public function process(\DOMElement $element, $expression)
    {
        $prev = $element->previousSibling;
        if (!$prev->hasAttribute('v-if')) {
            throw new \LogicException('v-else-if must after v-if');
        }

        $expression = $this->resolveExpression($expression);

        $startCode = 'elseif (' . $expression . ') { ';
        $endCode = '} ';

        $this->before($element, $startCode);
        $this->after($element, $endCode);
    }
}