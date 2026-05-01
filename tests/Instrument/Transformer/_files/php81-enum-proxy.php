<?php
declare(strict_types=1);
namespace Test\ns1;
use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Intercept\DynamicMethodInvocation;
enum TestStatus : string implements \Go\Aop\Proxy
{
    use \Test\ns1\TestStatus__AopProxied {
        \Test\ns1\TestStatus__AopProxied::label as private __aop__label;
    }
    case Active = 'active';
    case Inactive = 'inactive';
    public function label(): string
    {
        /** @var DynamicMethodInvocation<self, string> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'label', ['advisor.Test\ns1\TestStatus->label'], $this->__aop__label(...));
        return $__joinPoint->__invoke($this);
    }
}
