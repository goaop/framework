<?php
declare(strict_types=1);
namespace Test\ns1;
enum TestStatus : string implements \Go\Aop\Proxy
{
    use \Test\ns1\TestStatus__AopProxied {
        \Test\ns1\TestStatus__AopProxied::label as private __aop__label;
    }
    case Active = 'active';
    case Inactive = 'inactive';
    public function label(): string
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self>|null $__joinPoint */
        static $__joinPoint;
        if ($__joinPoint === null) {
            $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(self::class, 'label', ['advisor.Test\ns1\TestStatus->label']);
        }
        return $__joinPoint->__invoke($this);
    }
}
