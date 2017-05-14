<?php

namespace Hail\Template\Attributes;

class VueBind extends AbstractAttribute
{
    const name = 'v-bind';

    public function process(\DOMElement $element, $expression)
    {
        $attributes = $this->findBindAttribute($element);
        if ($attributes === []) {
            return;
        }

        foreach ($attributes as $attr => $val) {
            $element->setAttribute($attr, '<?php echo $' . $val . '; ?>');
        }
    }

    protected function findBindAttribute(\DOMElement $element)
    {
        $found = [];
        foreach ($element->attributes as $attribute) {
            $attr = $attribute->nodeName;
            if (
                strpos($attr, 'v-bind:') === 0 ||
                strpos($attr, ':') === 0
            ) {
                $attr = explode(':', $attr, 2)[1];
                $found[$attr] = trim($attribute->nodeValue);
            }
        }

        return $found;
    }

}