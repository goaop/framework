<?php
declare(strict_types=1);
namespace Test\ns1;
enum TestStatus : string implements \Go\Aop\Proxy
{
    use TestStatusOriginalTrait {
        TestStatusOriginalTrait::label as private labelOriginalAlias;
    }
    case Active = 'active';
    case Inactive = 'inactive';
    public function label(): string
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self, string> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'label',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestStatus->label')),
            ],
            $this->labelOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this);
    }
}
