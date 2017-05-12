<?php
namespace Hail\Template\Resolvers;

/**
 * Created by PhpStorm.
 * User: lenovo
 * Date: 2017/3/21
 * Time: 10:13
 */
class TALResolver implements SyntaxResolver
{
    /**
     * 
     * Resolve the expression.
     *
     * @param string $expression The expression to be resolved
     * @return string resolved php code.
     */
    function resolve($expression)
    {
        // TODO: Need to add fully compatibility with tal syntax
        $expression = trim($expression);
        // 将phptal的表达式转化为php的代码

        // php格式
        if (strpos($expression, 'php:') === 0) {
            $exp = preg_replace('/php:/', '', $expression);
            // 替换repeat.index
            $exp = preg_replace('/repeat\.\w+\.index/', '$_index == 0', $exp);
            // 替换repeat.end
            $exp = preg_replace('/repeat\.(\w+)\.end/', '$_index == count($$1)', $exp);
            // 替换is_mini
            $exp = str_replace('is_mini', '$is_mini', $exp);
            // 里面的variable格式需要替换。不带$符号，且有数组形式
            if (strpos($exp, '$') === false && preg_match('/(\w+)\[/', $exp, $matches) === 1) {
                $exp = preg_replace('/(\w+)\[/', '$$1[', $exp);
            }
            return $exp;
        }

        if (strpos($expression, 'not:') === 0) {
            $exp = '!(' . $this->resolve(preg_replace('/not:/', '', $expression)) . ')';
            return $exp;
        }

        $exp = $expression;
        // 替换repeat.index
        $exp = preg_replace('/repeat\/\w+\/index/', '$_index == 0', $exp);
        // 替换repeat.end
        $exp = preg_replace('/repeat\/(\w+)\/end/', '$_index == count($$1)', $exp);

        if (empty($exp)) {
            return $exp;
        }
        // phptal格式
        if (strpos($exp, '$') === false) {

            $arr = explode('/', $expression);
            $str = '$';
            for ($i = 0; $i < count($arr); $i++) {
                if ($i ==1) {
                    $str .= "['";
                } else if ($i > 1 && $i < count($arr)) {
                    $str .= "']['";
                }

                $str .= $arr[$i];

                if ($i >= 1 && $i == count($arr)-1) {
                    $str .= "']";
                }
            }

            return $str;
        }

        return $exp;
    }
}