<?php
declare(strict_types = 1);

namespace Go\Stubs;

use Go\Aop\Intercept\Invocation;

class FirstStatic extends First
{
    /**
     * @var Invocation|null
     */
    protected static $invocation = null;

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
