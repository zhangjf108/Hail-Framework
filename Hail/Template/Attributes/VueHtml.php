<?php
namespace Hail\Template\Attributes;

class VueHtml extends AbstractAttribute
{
    public $name = 'v-html';

    public function process(\DOMElement $element, $expression)
    {
        $expression = trim($expression);

        $this->text($element, 'echo htmlspecialchars($' . $expression . ');');
        
        return true;
    }
}