<?php
namespace Hail\Template\Attributes;

class VueContent extends AbstractAttribute
{
    public $name = 'v-content';

    public function process(\DOMElement $element, $expression)
    {
        $expression = trim($expression);

        $this->text($element, 'echo $' . $expression . ';');
        
        $element->removeAttribute($this->name);
    }
}