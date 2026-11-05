<?php
declare(strict_types=1);
namespace Test\ns1;

use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Framework\Interceptor;
use Go\Aop\Framework\The;
use Go\Aop\Intercept\DynamicMethodInvocation;
class TestAttributeArgsClass implements \Go\Aop\Proxy
{
    use TestAttributeArgsClassOriginalTrait {
        TestAttributeArgsClassOriginalTrait::tagged as private taggedOriginalAlias;
        TestAttributeArgsClassOriginalTrait::collected as private collectedOriginalAlias;
    }
    #[\Test\ns1\RichValueAttr(\Test\ns1\AttrStatus::Disabled, PHP_INT_MAX)]
    public function tagged(
        #[\Test\ns1\RichValueAttr(\Test\ns1\AttrStatus::Active)]
        int $x = 8
    ): int
    {
        /** @var DynamicMethodInvocation<self, int> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'tagged',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestAttributeArgsClass->tagged')),
            ],
            $this->taggedOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, \array_slice([$x], 0, \func_num_args()));
    }
    #[\Test\ns1\RichValueAttr(\Test\ns1\AttrStatus::Active, new \ArrayObject([1, 2]))]
    public function collected(): array
    {
        /** @var DynamicMethodInvocation<self, array> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'collected',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestAttributeArgsClass->collected')),
            ],
            $this->collectedOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this);
    }
}