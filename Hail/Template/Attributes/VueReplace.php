<?php

namespace Hail\Template\Attributes;

class VueReplace extends AbstractAttribute
{
    const name = 'v-replace';

    public function process(\DOMElement $element, $expression)
    {
        $expression = $this->resolveExpression($expression);

        $this->replace($element, $expression);

        return true;
    }
}