<?php
namespace Hail\Template\Attributes;

class VueText extends AbstractAttribute
{
    const name = 'v-text';

    public function process(\DOMElement $element, $expression)
    {
        $expression = trim($expression);

        $this->text($element, 'echo $' . $expression . ';');
    }
}