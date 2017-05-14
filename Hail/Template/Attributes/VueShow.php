<?php

namespace Hail\Template\Attributes;

class VueShow extends AbstractAttribute
{
    const name = 'v-show';

    public function process(\DOMElement $element, $expression)
    {
        if ($element->tagName === 'template') {
            throw  new \LogicException('v-show not support template tag');
        }

        $expression = $this->resolveExpression($expression);

        $style = '<?php echo (' . $expression . ') ? \'\': \'display: none\' ?>';
        $this->addStyle($element, $style);
    }
}