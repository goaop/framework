<?php
declare(strict_types=1);
namespace Test\ns1;
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
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self, string> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'overriddenMethod',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestClassWithOverride->overriddenMethod')),
            ],
            $this->overriddenMethodOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this);
    }
    public function normalMethod(): int
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self, int> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'normalMethod',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestClassWithOverride->normalMethod')),
            ],
            $this->normalMethodOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this);
    }
}
