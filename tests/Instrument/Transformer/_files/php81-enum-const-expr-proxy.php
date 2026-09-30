<?php
declare(strict_types=1);
namespace Test\ns1;

enum ConstExprStatus : int implements \Go\Aop\Proxy
{
    use ConstExprStatusOriginalTrait {
        ConstExprStatusOriginalTrait::describe as private describeOriginalAlias;
    }
    case Negative = -1;
    case Shifted = 1 << 2;
    case FromConst = self::SHIFT + 10;
    public function describe(): string
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self, string> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'describe',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\ConstExprStatus->describe')),
            ],
            $this->describeOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this);
    }
}