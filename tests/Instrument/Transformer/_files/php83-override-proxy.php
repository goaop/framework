<?php
declare(strict_types=1);
namespace Test\ns1;
/**
 * PHP 8.3 — class with #[\Override] on an intercepted method.
 * WeavingTransformer must strip the attribute from the generated trait so that
 * the proxy's __aop__overriddenMethod alias does not trigger a fatal error.
 */
class TestClassWithOverride implements \Go\Aop\Proxy
{
    use \Test\ns1\TestClassWithOverride__AopProxied {
        \Test\ns1\TestClassWithOverride__AopProxied::overriddenMethod as private __aop__overriddenMethod;
        \Test\ns1\TestClassWithOverride__AopProxied::normalMethod as private __aop__normalMethod;
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
    #[\Override]
    public function overriddenMethod(): string
    {
        return self::$__joinPoints['method:overriddenMethod']->__invoke($this);
    }
    public function normalMethod(): int
    {
        return self::$__joinPoints['method:normalMethod']->__invoke($this);
    }
}
\Go\Proxy\ClassProxyGenerator::injectJoinPoints(TestClassWithOverride::class, [
    'method' => [
        'overriddenMethod' => [
            'advisor.Test\ns1\TestClassWithOverride->overriddenMethod',
        ],
        'normalMethod' => [
            'advisor.Test\ns1\TestClassWithOverride->normalMethod',
        ],
    ],
]);
