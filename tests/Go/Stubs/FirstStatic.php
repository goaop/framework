<?php

namespace Go\Stubs;

use Go\Aop\Framework\ClosureStaticMethodInvocation;

class FirstStatic extends First
{
    /**
     * @var ClosureStaticMethodInvocation|null
     */
    protected static $invocation = null;

    public function __construct(ClosureStaticMethodInvocation $invocation)
    {
        static::$invocation = $invocation;
    }

    // Recursion test
    public static function staticLsbRecursion($value, $level = 0)
    {
        return static::$invocation->__invoke(__CLASS__, array($value, $level));
    }
}
