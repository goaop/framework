<?php
declare(strict_types=1);
namespace Test\ns1;
final class TestReadonlyClass implements \Go\Aop\Proxy
{
    use \Test\ns1\TestReadonlyClass__AopProxied {
        \Test\ns1\TestReadonlyClass__AopProxied::publicMethod as private __aop__publicMethod;
        \Test\ns1\TestReadonlyClass__AopProxied::anotherMethod as private __aop__anotherMethod;
        \Test\ns1\TestReadonlyClass__AopProxied::staticMethod as private __aop__staticMethod;
    }
    /**
     * List of applied advices per class
     *
     * Typed as MethodInvocation because generated method bodies (method:* and static:* keys)
     * call ->__invoke() directly. Other joinpoint types stored here use explicit casts:
     *   - prop:*        ClassFieldAccess — used in generated native property hooks
     *   - staticinit:*  StaticInitializationJoinpoint — instanceof check in ClassProxyGenerator::injectJoinPoints()
     *   - init:*        ReflectionConstructorInvocation — accessed via ConstructorExecutionTransformer
     *
     * @var array<string, \Go\Aop\Intercept\MethodInvocation>
     */
    private static array $__joinPoints = [];
    public function publicMethod(): string
    {
        return self::$__joinPoints['method:publicMethod']->__invoke($this);
    }
    public function anotherMethod(int $x): int
    {
        return self::$__joinPoints['method:anotherMethod']->__invoke($this, [$x]);
    }
    public static function staticMethod(): string
    {
        return self::$__joinPoints['static:staticMethod']->__invoke(static::class);
    }
}
\Go\Proxy\ClassProxyGenerator::injectJoinPoints(TestReadonlyClass::class, [
    'method' => [
        'publicMethod' => [
            'advisor.Test\ns1\TestReadonlyClass->publicMethod',
        ],
        'anotherMethod' => [
            'advisor.Test\ns1\TestReadonlyClass->anotherMethod',
        ],
        'staticMethod' => [
            'advisor.Test\ns1\TestReadonlyClass->staticMethod',
        ],
    ],
]);
