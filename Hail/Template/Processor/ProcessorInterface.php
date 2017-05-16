<?php

namespace Hail\Template\Processor;


interface ProcessorInterface
{
    public function attribute(): string;

    public function process(\DOMElement $element, $expression);
}