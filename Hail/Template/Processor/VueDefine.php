<?php
namespace Hail\Template\Processor;

class VueDefine extends AbstractProcessor
{
    public function attribute(): string
    {
        return 'v-define';
    }

    public function process(\DOMElement $element, $expression)
    {
        list($name, $var) = explode('=', $expression, 2);
        $startCode = trim($name) . ' = ' . trim($var) . '; ';
        
        $this->before($element, $startCode);
    }
}