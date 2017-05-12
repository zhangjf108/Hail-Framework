<?php
namespace Hail\Template\Resolvers;

/**
 * SyntaxResolver solves how to convert expressions to php codes. 
 * 
 * You can accomplish your own resolver to resolve different syntax like standard TAL format.
 * 
 * User: softwarezhu
 * Date: 2017/3/20
 * Time: 下午11:28
 */
interface SyntaxResolver
{
    /**
     * Resolve the expression. 
     * 
     * @param string $expression The expression to be resolved
     * @return string resolved php code. 
     */
    public function resolve($expression);
}