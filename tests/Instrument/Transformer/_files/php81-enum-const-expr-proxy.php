<?php
declare(strict_types=1);
namespace Test\ns1;

use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Framework\Interceptor;
use Go\Aop\Framework\The;
use Go\Aop\Intercept\DynamicMethodInvocation;
enum ConstExprStatus : int implements \Go\Aop\Proxy
{
    use ConstExprStatus__AopProxied {
        ConstExprStatus__AopProxied::describe as private __aop__describe;
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
            $this->__aop__describe(...),
        );
        return $__joinPoint->__invoke($this);
    }
}