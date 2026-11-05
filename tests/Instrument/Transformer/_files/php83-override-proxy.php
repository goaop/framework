<?php
declare(strict_types=1);
namespace Test\ns1;
use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Framework\Interceptor;
use Go\Aop\Framework\The;
use Go\Aop\Intercept\DynamicMethodInvocation;
/**
 * PHP 8.3 — class with #[\Override] on an intercepted method.
 * WeavingTransformer must strip the attribute from the generated trait so that
 * the proxy's overriddenMethodOriginalAlias alias does not trigger a fatal error.
 */
class TestClassWithOverride implements \Go\Aop\Proxy
{
    use TestClassWithOverrideOriginalTrait {
        TestClassWithOverrideOriginalTrait::overriddenMethod as private overriddenMethodOriginalAlias;
        TestClassWithOverrideOriginalTrait::normalMethod as private normalMethodOriginalAlias;
    }
    #[\Override]
    public function overriddenMethod(): string
    {
        /** @var DynamicMethodInvocation<self, string> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'overriddenMethod',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestClassWithOverride->overriddenMethod')),
            ],
            $this->overriddenMethodOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this);
    }
    public function normalMethod(): int
    {
        /** @var DynamicMethodInvocation<self, int> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'normalMethod',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestClassWithOverride->normalMethod')),
            ],
            $this->normalMethodOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this);
    }
}
