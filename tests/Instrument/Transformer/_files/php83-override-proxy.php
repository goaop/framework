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
    #[\Override]
    public function overriddenMethod(): string
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self, string>|null $__joinPoint */
        static $__joinPoint;
        if ($__joinPoint === null) {
            $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(self::class, 'overriddenMethod', ['advisor.Test\ns1\TestClassWithOverride->overriddenMethod']);
        }
        return $__joinPoint->__invoke($this);
    }
    public function normalMethod(): int
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self, int>|null $__joinPoint */
        static $__joinPoint;
        if ($__joinPoint === null) {
            $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(self::class, 'normalMethod', ['advisor.Test\ns1\TestClassWithOverride->normalMethod']);
        }
        return $__joinPoint->__invoke($this);
    }
}
