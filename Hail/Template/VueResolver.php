<?php
namespace Hail\Template;

class VueResolver
{
    /**
     * This resolver treat the expression as php code, so it just returns it's trimed expressions.
     * @param string $expression
     * @return string
     */
    public function resolve($expression)
    {
        return trim($expression);
    }

}