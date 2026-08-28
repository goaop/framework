<?php
declare(strict_types=1);
namespace Test\ns1;

use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Intercept\DynamicMethodInvocation;
class TestAttributeArgsClass implements \Go\Aop\Proxy
{
    use TestAttributeArgsClass__AopProxied {
        TestAttributeArgsClass__AopProxied::tagged as private __aop__tagged;
        TestAttributeArgsClass__AopProxied::collected as private __aop__collected;
    }
    #[\Test\ns1\RichValueAttr(\Test\ns1\AttrStatus::Disabled, PHP_INT_MAX)]
    public function tagged(
        #[\Test\ns1\RichValueAttr(\Test\ns1\AttrStatus::Active)]
        int $x = 8
    ): int
    {
        /** @var DynamicMethodInvocation<self, int> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'tagged', ['advisor.Test\ns1\TestAttributeArgsClass->tagged'], $this->__aop__tagged(...));
        return $__joinPoint->__invoke($this, \array_slice([$x], 0, \func_num_args()));
    }
    #[\Test\ns1\RichValueAttr(\Test\ns1\AttrStatus::Active, new \ArrayObject([1, 2]))]
    public function collected(): array
    {
        /** @var DynamicMethodInvocation<self, array> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'collected', ['advisor.Test\ns1\TestAttributeArgsClass->collected'], $this->__aop__collected(...));
        return $__joinPoint->__invoke($this);
    }
}