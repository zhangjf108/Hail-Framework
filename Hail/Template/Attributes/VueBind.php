<?php

namespace Hail\Template\Attributes;

class VueBind extends AbstractAttribute
{
    public $name = 'v-bind';

    public function process(\DOMElement $element, $expression)
    {
        foreach ($expressions as $expression) {
            $expression = trim($expression);
            if (empty($expression)) {
                continue;
            }
            [$attr, $val] = explode('=', $expression);

            $element->setAttribute(trim($attr), '<?php echo $' . trim($val) . '; ?>');
        }
    }
}