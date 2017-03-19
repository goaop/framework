<?php
declare(strict_types = 1);

namespace Go\Stubs;

class First
{

    private $private = T_PRIVATE;
    protected $protected = T_PROTECTED;
    public $public = T_PUBLIC;

    private static $staticPrivate = T_PRIVATE;
    protected static $staticProtected = T_PROTECTED;
    protected static $staticPublic = T_PUBLIC;

    // Dynamic methods that access $this-> properties
    private function privateMethod()
    {
        return $this->private;
    }

    protected function protectedMethod()
    {
        return $this->protected;
    }

    public function publicMethod()
    {
        return $this->public;
    }

    // Static methods that access self:: properties
    private static function staticSelfPrivate()
    {
        return self::$staticPrivate;
    }

    protected static function staticSelfProtected()
    {
        return self::$staticProtected;
    }

    public static function staticSelfPublic()
    {
        return self::$staticPublic;
    }

    public static function staticSelfPublicAccessPrivate()
    {
        return self::$staticPrivate;
    }

    // Static methods that access static:: properties with LSB
    protected static function staticLsbProtected()
    {
        return get_called_class();
    }

    public static function staticLsbPublic()
    {
        return get_called_class();
    }

    // Pass by reference
    public function passByReference(&$valueByReference)
    {
        $valueByReference = null;
        return $valueByReference;
    }

    // Pass by reference
    public static function staticPassByReference(&$valueByReference)
    {
        $valueByReference = null;
        return $valueByReference;
    }

    // Recursion test
    public function recursion($value, $level = 0)
    {
        if ($level > 0) {
            $value += $this->recursion($value, $level-1);
        }
        return $value;
    }

    // Recursion test
    public static function staticLsbRecursion($value, $level = 0)
    {
        if ($level > 0) {
            $value += static::staticLsbRecursion($value, $level-1);
        }
        return $value;
    }

    /**
     * Method for checking invocation with any number of arguments
     *
     * NB: Real proxy use the method definition to prepare invocation proxy, so variable number of arguments
     * will not work at all!
     *
     * @return string
     */
    public function variableArgsTest()
    {
        return join('', func_get_args());
    }

    /**
     * Method for checking variadic arguments
     *
     * @return array
     */
    public function variadicArgsTest(...$args)
    {
        return join('', $args);
    }

    /**
     * Method for checking invocation with any number of arguments
     *
     * NB: Real proxy use the method definition to prepare invocation proxy, so variable number of arguments
     * will not work at all!
     *
     * @return string
     */
    public static function staticVariableArgsTest()
    {
        return join('', func_get_args());
    }

    /**
     * Method for checking static variadic arguments
     *
     * @return array
     */
    public static function staticVariadicArgsTest(...$args)
    {
        return join('', $args);
    }
}
