<?php

namespace Hail\Template\Processor;

class VueShow extends AbstractProcessor
{
    public function attribute(): string
    {
        return 'v-show';
    }

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