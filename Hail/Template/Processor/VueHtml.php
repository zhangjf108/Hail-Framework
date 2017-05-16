<?php
namespace Hail\Template\Processor;

class VueHtml extends AbstractProcessor
{
    public function attribute(): string
    {
        return 'v-html';
    }

    public function process(\DOMElement $element, $expression)
    {
        $expression = trim($expression);

        $this->text($element, 'echo htmlspecialchars($' . $expression . ');');
    }
}