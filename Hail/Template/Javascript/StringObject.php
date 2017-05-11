<?php

namespace Hail\Template\Javascript;

class StaticString
{
    /**
     * Wrapper for substr
     */
    public static function substr($string, $start, $length = null)
    {
        return new StringObject(mb_substr($string, $start, $length));
    }

    /**
     * Equivelent of Javascript's String.substring
     *
     * @link http://www.w3schools.com/jsref/jsref_substring.asp
     */
    public static function substring($string, $start, $end)
    {
        if (empty($length)) {
            return self::substr($string, $start);
        }

        return self::substr($string, $end - $start);
    }

    public function charAt($str, $point)
    {
        return self::substr($str, $point, 1);
    }

    public function charCodeAt($str, $point)
    {
        return ord(self::substr($str, $point, 1));
    }

    public static function concat(...$args)
    {
        $r = '';
        foreach ($args as $arg) {
            $r .= (string) $arg;
        }

        return $r;
    }

    public static function fromCharCode($code)
    {
        return chr($code);
    }

    public static function indexOf($haystack, $needle, $offset = 0)
    {
        return mb_strpos($haystack, $needle, $offset);
    }

    public static function lastIndexOf($haystack, $needle, $offset = 0)
    {
        return mb_strrpos($haystack, $needle, $offset);
    }

    public static function match($haystack, $regex)
    {
        preg_match_all($regex, $haystack, $matches, PREG_PATTERN_ORDER);

        return new ArrayObject($matches[0]);
    }

    public static function replace($haystack, $needle, $replace, $regex = false)
    {
        if ($regex) {
            $r = preg_replace($needle, $replace, $haystack);
        } else {
            $r = str_replace($needle, $replace, $haystack);
        }

        return new StringObject($r);
    }

    public static function strlen($string)
    {
        return mb_strlen($string);
    }

    public static function slice($string, $start, $end = null)
    {
        return self::substring($string, $start, $end);
    }

    public static function toLowerCase($string)
    {
        return new StringObject(mb_strtolower($string));
    }

    public static function toUpperCase($string)
    {
        return new StringObject(mb_strtoupper($string));
    }

    public static function split($string, $at = '')
    {
        if (empty($at)) {
            return new ArrayObject(str_split($string));
        }

        return new ArrayObject(explode($at, $string));
    }
}

/**
 * Class StringObject
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String
 *
 * @package Hail\Template\Javascript
 */
class StringObject implements \ArrayAccess
{
    /**
     * @var string
     */
    private $value;

    public function __construct(string $string)
    {
        $this->value = $string;
    }

    public function __toString()
    {
        return $this->value;
    }

    /* end magic methods */

    /* ArrayAccess Methods */

    /** offsetExists ( mixed $index )
     *
     * Similar to array_key_exists
     */
    public function offsetExists($index)
    {
        return !empty($this->value[$index]);
    }

    /* offsetGet ( mixed $index )
     *
     * Retrieves an array value
     */
    public function offsetGet($index)
    {
        return StaticString::substr($this->value, $index, 1)->toString();
    }

    /* offsetSet ( mixed $index, mixed $val )
     *
     * Sets an array value
     */
    public function offsetSet($index, $val)
    {
        $this->value = StaticString::substring($this->value, 0, $index) . $val . StaticString::substring($this->value,
                $index + 1, StaticString::strlen($this->value));
    }

    /* offsetUnset ( mixed $index )
     *
     * Removes an array value
     */
    public function offsetUnset($index)
    {
        $this->value = StaticString::substr($this->value, 0, $index) . StaticString::substr($this->value, $index + 1);
    }

    public static function create($obj)
    {
        if ($obj instanceof StringObject) {
            return $obj;
        }

        return new StringObject($obj);
    }

    /* public methods */
    public function substr($start, $length)
    {
        return StaticString::substr($this->value, $start, $length);
    }

    public function substring($start, $end)
    {
        return StaticString::substring($this->value, $start, $end);
    }

    public function charAt($point)
    {
        return StaticString::substr($this->value, $point, 1);
    }

    public function charCodeAt($point)
    {
        return ord(StaticString::substr($this->value, $point, 1));
    }

    public function indexOf($needle, $offset)
    {
        return StaticString::indexOf($this->value, $needle, $offset);
    }

    public function lastIndexOf($needle)
    {
        return StaticString::lastIndexOf($this->value, $needle);
    }

    public function match($regex)
    {
        return StaticString::match($this->value, $regex);
    }

    public function replace($search, $replace, $regex = false)
    {
        return StaticString::replace($this->value, $search, $replace, $regex);
    }

    public function first()
    {
        return StaticString::substr($this->value, 0, 1);
    }

    public function last()
    {
        return StaticString::substr($this->value, -1, 1);
    }

    public function search($search, $offset = null)
    {
        return $this->indexOf($search, $offset);
    }

    public function slice($start, $end = null)
    {
        return StaticString::slice($this->value, $start, $end);
    }

    public function toLowerCase()
    {
        return StaticString::toLowerCase($this->value);
    }

    public function toUpperCase()
    {
        return StaticString::toUpperCase($this->value);
    }

    public function toUpper()
    {
        return $this->toUpperCase();
    }

    public function toLower()
    {
        return $this->toLowerCase();
    }

    public function split($at = '')
    {
        return StaticString::split($this->value, $at);
    }

    public function trim($charlist = null)
    {
        return new StringObject(trim($this->value, $charlist));
    }

    public function ltrim($charlist = null)
    {
        return new StringObject(ltrim($this->value, $charlist));
    }

    public function rtrim($charlist = null)
    {
        return new StringObject(rtrim($this->value, $charlist));
    }

    public function toString()
    {
        return $this->__toString();
    }
}