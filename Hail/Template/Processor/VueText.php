<?php
namespace Hail\Template\Processor;

class VueText extends AbstractProcessor
{
    public function attribute(): string
    {
        return 'v-text';
    }

    public function process(\DOMElement $element, $expression)
    {
        $expression = trim($expression);

        $this->text($element, 'echo $' . $expression . ';');
    }
}