<?php
namespace Hail\Template\Attributes;

class VueDefine extends AbstractAttribute
{
    public $name = 'tal:define';

    public function process(\DOMElement $element, $expression)
    {
        list($name, $var) = $this->splitExpression($expression);
        $name = $this->resolveExpression($name);
        $var = $this->resolveExpression($var);
        $startCode = $name . ' = ' . $var . '; ';
        
        $this->before($element, $startCode);
       
        $element->removeAttribute($this->name);
    }
}