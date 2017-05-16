<?php

namespace Hail\Template\Processor;

class VueReplace extends AbstractProcessor
{
    public function attribute(): string
    {
        return 'v-replace';
    }

    public function process(\DOMElement $element, $expression)
    {
        $expression = $this->resolveExpression($expression);

        $this->replace($element, $expression);

        return true;
    }
}