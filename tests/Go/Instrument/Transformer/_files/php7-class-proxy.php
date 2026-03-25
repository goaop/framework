<?php
declare(strict_types=1);
namespace Test\ns1;
class TestPhp7Class implements \Go\Aop\Proxy
{
    use \Test\ns1\TestPhp7Class__AopProxied {
        \Test\ns1\TestPhp7Class__AopProxied::stringSth as private __aop__stringSth;
        \Test\ns1\TestPhp7Class__AopProxied::floatSth as private __aop__floatSth;
        \Test\ns1\TestPhp7Class__AopProxied::boolSth as private __aop__boolSth;
        \Test\ns1\TestPhp7Class__AopProxied::intSth as private __aop__intSth;
        \Test\ns1\TestPhp7Class__AopProxied::callableSth as private __aop__callableSth;
        \Test\ns1\TestPhp7Class__AopProxied::arraySth as private __aop__arraySth;
        \Test\ns1\TestPhp7Class__AopProxied::variadicStringSthByRef as private __aop__variadicStringSthByRef;
        \Test\ns1\TestPhp7Class__AopProxied::exceptionArg as private __aop__exceptionArg;
        \Test\ns1\TestPhp7Class__AopProxied::stringRth as private __aop__stringRth;
        \Test\ns1\TestPhp7Class__AopProxied::floatRth as private __aop__floatRth;
        \Test\ns1\TestPhp7Class__AopProxied::boolRth as private __aop__boolRth;
        \Test\ns1\TestPhp7Class__AopProxied::intRth as private __aop__intRth;
        \Test\ns1\TestPhp7Class__AopProxied::callableRth as private __aop__callableRth;
        \Test\ns1\TestPhp7Class__AopProxied::arrayRth as private __aop__arrayRth;
        \Test\ns1\TestPhp7Class__AopProxied::exceptionRth as private __aop__exceptionRth;
        \Test\ns1\TestPhp7Class__AopProxied::noRth as private __aop__noRth;
        \Test\ns1\TestPhp7Class__AopProxied::returnSelf as private __aop__returnSelf;
    }
    /**
     * List of applied advices per class
     *
     * Typed as MethodInvocation because generated method bodies (method:* and static:* keys)
     * call ->__invoke() directly. Other joinpoint types stored here use explicit casts:
     *   - prop:*        ClassFieldAccess — cast in PropertyInterceptionTrait
     *   - staticinit:*  StaticInitializationJoinpoint — instanceof check in ClassProxyGenerator::injectJoinPoints()
     *   - init:*        ReflectionConstructorInvocation — accessed via ConstructorExecutionTransformer
     *
     * @var array<string, \Go\Aop\Intercept\MethodInvocation>
     */
    private static array $__joinPoints = [];
    public function stringSth(string $arg)
    {
        return self::$__joinPoints['method:stringSth']->__invoke($this, [$arg]);
    }
    public function floatSth(float $arg)
    {
        return self::$__joinPoints['method:floatSth']->__invoke($this, [$arg]);
    }
    public function boolSth(bool $arg)
    {
        return self::$__joinPoints['method:boolSth']->__invoke($this, [$arg]);
    }
    public function intSth(int $arg)
    {
        return self::$__joinPoints['method:intSth']->__invoke($this, [$arg]);
    }
    public function callableSth(callable $arg)
    {
        return self::$__joinPoints['method:callableSth']->__invoke($this, [$arg]);
    }
    public function arraySth(array $arg)
    {
        return self::$__joinPoints['method:arraySth']->__invoke($this, [$arg]);
    }
    public function variadicStringSthByRef(string &...$args)
    {
        return self::$__joinPoints['method:variadicStringSthByRef']->__invoke($this, $args);
    }
    public function exceptionArg(\Exception $exception, \Test\ns1\Exception $localException)
    {
        return self::$__joinPoints['method:exceptionArg']->__invoke($this, [$exception, $localException]);
    }
    public function stringRth(string $arg): string
    {
        return self::$__joinPoints['method:stringRth']->__invoke($this, [$arg]);
    }
    public function floatRth(float $arg): float
    {
        return self::$__joinPoints['method:floatRth']->__invoke($this, [$arg]);
    }
    public function boolRth(bool $arg): bool
    {
        return self::$__joinPoints['method:boolRth']->__invoke($this, [$arg]);
    }
    public function intRth(int $arg): int
    {
        return self::$__joinPoints['method:intRth']->__invoke($this, [$arg]);
    }
    public function callableRth(callable $arg): callable
    {
        return self::$__joinPoints['method:callableRth']->__invoke($this, [$arg]);
    }
    public function arrayRth(array $arg): array
    {
        return self::$__joinPoints['method:arrayRth']->__invoke($this, [$arg]);
    }
    public function exceptionRth(\Exception $exception): \Exception
    {
        return self::$__joinPoints['method:exceptionRth']->__invoke($this, [$exception]);
    }
    public function noRth(\Test\ns1\LocalException $exception)
    {
        return self::$__joinPoints['method:noRth']->__invoke($this, [$exception]);
    }
    /**
     * `self` value is handled on AST level via SelfValueTransformer class
     * @see \Go\Instrument\Transformer\SelfValueTransformer
     */
    public function returnSelf()
    {
        return self::$__joinPoints['method:returnSelf']->__invoke($this);
    }
}
\Go\Proxy\ClassProxyGenerator::injectJoinPoints(TestPhp7Class::class, [
    'method' => [
        'stringSth' => [
            'advisor.Test\ns1\TestPhp7Class->stringSth',
        ],
        'floatSth' => [
            'advisor.Test\ns1\TestPhp7Class->floatSth',
        ],
        'boolSth' => [
            'advisor.Test\ns1\TestPhp7Class->boolSth',
        ],
        'intSth' => [
            'advisor.Test\ns1\TestPhp7Class->intSth',
        ],
        'callableSth' => [
            'advisor.Test\ns1\TestPhp7Class->callableSth',
        ],
        'arraySth' => [
            'advisor.Test\ns1\TestPhp7Class->arraySth',
        ],
        'variadicStringSthByRef' => [
            'advisor.Test\ns1\TestPhp7Class->variadicStringSthByRef',
        ],
        'exceptionArg' => [
            'advisor.Test\ns1\TestPhp7Class->exceptionArg',
        ],
        'stringRth' => [
            'advisor.Test\ns1\TestPhp7Class->stringRth',
        ],
        'floatRth' => [
            'advisor.Test\ns1\TestPhp7Class->floatRth',
        ],
        'boolRth' => [
            'advisor.Test\ns1\TestPhp7Class->boolRth',
        ],
        'intRth' => [
            'advisor.Test\ns1\TestPhp7Class->intRth',
        ],
        'callableRth' => [
            'advisor.Test\ns1\TestPhp7Class->callableRth',
        ],
        'arrayRth' => [
            'advisor.Test\ns1\TestPhp7Class->arrayRth',
        ],
        'exceptionRth' => [
            'advisor.Test\ns1\TestPhp7Class->exceptionRth',
        ],
        'noRth' => [
            'advisor.Test\ns1\TestPhp7Class->noRth',
        ],
        'returnSelf' => [
            'advisor.Test\ns1\TestPhp7Class->returnSelf',
        ],
    ],
]);
