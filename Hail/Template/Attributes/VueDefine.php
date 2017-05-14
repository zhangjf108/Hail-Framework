<?php
namespace Hail\Template\Attributes;

class VueDefine extends AbstractAttribute
{
    const name = 'v-define';

    public function process(\DOMElement $element, $expression)
    {
        list($name, $var) = explode('=', $expression, 2);
        $startCode = trim($name) . ' = ' . trim($var) . '; ';
        
        $this->before($element, $startCode);
    }
}