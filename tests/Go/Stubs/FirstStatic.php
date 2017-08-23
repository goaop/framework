<?php

namespace Go\Stubs;

use Go\Aop\Intercept\Invocation;

class FirstStatic extends First
{
    /**
     * @var Invocation|null
     */
    protected static $invocation;

    public function __construct(Invocation $invocation)
    {
        static::$invocation = $invocation;
    }

    // Recursion test
    public static function staticLsbRecursion($value, $level = 0)
    {
        return static::$invocation->__invoke(__CLASS__, [$value, $level]);
    }
}
