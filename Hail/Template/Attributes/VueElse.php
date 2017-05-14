<?php

namespace Hail\Template\Attributes;

class VueElse extends AbstractAttribute
{
    const name = 'v-else';

    public function process(\DOMElement $element, $expression)
    {
        $prev = $element->previousSibling;
        if (!$prev->hasAttribute('v-if') || !$prev->hasAttribute('v-else-if')) {
            throw new \LogicException('v-else must after v-if or v-else-if');
        }

        $startCode = 'else { ';
        $endCode = '} ';

        $this->before($element, $startCode);
        $this->after($element, $endCode);
    }
}