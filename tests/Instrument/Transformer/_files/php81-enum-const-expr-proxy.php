<?php
declare(strict_types=1);
namespace Test\ns1;

use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Framework\Interceptor;
use Go\Aop\Framework\The;
use Go\Aop\Intercept\DynamicMethodInvocation;
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
        /** @var DynamicMethodInvocation<self, string> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'describe',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\ConstExprStatus->describe')),
            ],
            $this->describeOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this);
    }
}