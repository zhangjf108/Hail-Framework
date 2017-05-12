<?php
namespace Hail\Template\Resolvers;

/**
 * Default resolver treat the expression as php code, so it just returns it's trimed expressions. 
 * 
 * User: softwarezhu
 * Date: 2017/3/20
 * Time: 下午11:29
 */
class DefaultResolver implements SyntaxResolver
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