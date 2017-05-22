<?php

namespace Hail\Template\Processor;

class VueBind extends AbstractProcessor
{
    public function attribute(): string
    {
        return 'v-bind';
    }

    public function process(\DOMElement $element, $expression)
    {
        foreach ($this->findBindAttribute($element) as $attr => $val) {
            $element->setAttribute($attr, '<?php echo $' . $val . '; ?>');
        }
    }

    protected function findBindAttribute(\DOMElement $element)
    {
        foreach ($element->attributes as $attribute) {
            $attr = $attribute->nodeName;
            if (
                strpos($attr, 'v-bind:') === 0 ||
                strpos($attr, ':') === 0
            ) {
                $attr = explode(':', $attr, 2)[1];
                yield $attr => trim($attribute->nodeValue);
            }
        }
    }

}