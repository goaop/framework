<?php
declare(strict_types=1);
namespace Test\ns1;

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
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self, int> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'tagged',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestAttributeArgsClass->tagged')),
            ],
            $this->taggedOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, \array_slice([$x], 0, \func_num_args()));
    }
    #[\Test\ns1\RichValueAttr(\Test\ns1\AttrStatus::Active, new \ArrayObject([1, 2]))]
    public function collected(): array
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self, array> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'collected',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestAttributeArgsClass->collected')),
            ],
            $this->collectedOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this);
    }
}