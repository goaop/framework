<?php
declare(strict_types=1);
namespace Test\ns1;
use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Framework\Interceptor;
use Go\Aop\Framework\The;
use Go\Aop\Intercept\DynamicMethodInvocation;
enum TestStatus : string implements \Go\Aop\Proxy
{
    use TestStatusOriginalTrait {
        TestStatusOriginalTrait::label as private labelOriginalAlias;
    }
    case Active = 'active';
    case Inactive = 'inactive';
    public function label(): string
    {
        /** @var DynamicMethodInvocation<self, string> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'label',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestStatus->label')),
            ],
            $this->labelOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this);
    }
}
